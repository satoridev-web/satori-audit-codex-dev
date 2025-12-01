<?php
/**
 * Plugin inventory service.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide helpers for reading and diffing plugin state.
 */
class Plugins_Service {
    /**
     * Retrieve plugin update history for a report or preview window.
     *
     * @param int $report_id Report identifier (0 for preview).
     *
     * @return array
     */
    public static function get_plugin_update_history( int $report_id = 0 ): array {
        $settings = Settings::get_settings();
        $range    = self::get_date_range( $report_id );

        $history = self::build_fallback_history( $report_id );

        $simple_history_updates = self::get_updates_from_simple_history( $range, $settings );

        if ( ! empty( $simple_history_updates ) ) {
            $history = array_merge( $history, $simple_history_updates );
        }

        self::log_debug(
            sprintf(
                'Compiled plugin update history for report %d (Simple History mode: %s). Total records: %d.',
                $report_id,
                $settings['plugin_update_source'] ?? 'none',
                count( $history )
            )
        );

        return $history;
    }

    /**
     * Retrieve the current plugins installed on the site, keyed by slug.
     *
     * @return array
     */
    public function get_current_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins      = get_plugins();
        $active       = (array) get_option( 'active_plugins', [] );
        $normalized   = [];
        $current_time = current_time( 'mysql', true );

        foreach ( $plugins as $file => $data ) {
            $slug = $this->normalise_slug( $file );

            $normalized[ $slug ] = [
                'plugin_slug'        => $slug,
                'plugin_name'        => $data['Name'] ?? $slug,
                'plugin_description' => $data['Description'] ?? '',
                'version_current'    => $data['Version'] ?? '',
                'is_active'          => in_array( $file, $active, true ) ? 1 : 0,
                'last_checked'       => $current_time,
            ];
        }

        return $normalized;
    }

    /**
     * Normalise a plugin file path into a slug.
     *
     * @param string $plugin_file Plugin file path.
     */
    private function normalise_slug( string $plugin_file ): string {
        $parts = explode( '/', $plugin_file );

        return sanitize_title( $parts[0] ?? $plugin_file );
    }

    /**
     * Determine if Simple History is active.
     */
    private static function is_simple_history_active(): bool {
        return class_exists( '\\Simple_History\\Simple_History' ) || class_exists( 'Simple_History' );
    }

    /**
     * Determine if the Simple History table exists.
     */
    private static function simple_history_table_exists( string $table = '' ): bool {
        global $wpdb;

        $table_name = ! empty( $table ) ? $table : $wpdb->prefix . 'simple_history';
        $found      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        return $table_name === $found;
    }

    /**
     * Calculate the date range used for update queries.
     *
     * @param int $report_id Report identifier (0 when previewing).
     */
    private static function get_date_range( int $report_id ): array {
        $now = current_time( 'mysql', true );

        if ( $report_id <= 0 ) {
            return array(
                'from' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days', strtotime( $now ) ) ),
                'to'   => $now,
            );
        }

        $period       = get_post_meta( $report_id, '_satori_audit_period', true );
        $period_start = ! empty( $period ) ? strtotime( $period . '-01 00:00:00' ) : false;

        if ( false === $period_start ) {
            return array(
                'from' => gmdate( 'Y-m-d H:i:s', strtotime( '-60 days', strtotime( $now ) ) ),
                'to'   => $now,
            );
        }

        $from = gmdate( 'Y-m-d H:i:s', strtotime( '-1 month', $period_start ) );
        $to   = gmdate( 'Y-m-t 23:59:59', $period_start );

        return array(
            'from' => $from,
            'to'   => $to,
        );
    }

    /**
     * Load plugin updates from Simple History events when enabled and schema-compatible.
     */
    private static function get_updates_from_simple_history( array $range, array $settings ): array {
        if ( 'simple_history_safe' !== (string) ( $settings['plugin_update_source'] ?? 'none' ) ) {
            return array();
        }

        global $wpdb;

        if ( ! self::is_simple_history_active() ) {
            self::log_simple_history( 'Simple History integration skipped: plugin not active.' );

            return array();
        }

        $table                 = $wpdb->prefix . 'simple_history';
        $previous_suppression  = $wpdb->suppress_errors( true );
        $columns               = array();
        $has_context_column    = false;
        $selectable_columns    = array( 'date', 'message' );

        if ( ! self::simple_history_table_exists( $table ) ) {
            $wpdb->suppress_errors( $previous_suppression );
            self::log_simple_history( 'Simple History integration skipped: table not found: ' . $table );

            return array();
        }

        $columns = self::get_simple_history_columns( $table );

        if ( empty( $columns ) ) {
            $wpdb->suppress_errors( $previous_suppression );
            self::log_simple_history( 'Simple History integration skipped: unable to read columns for ' . $table );

            return array();
        }

        foreach ( array( 'date', 'message' ) as $required ) {
            if ( ! in_array( $required, $columns, true ) ) {
                $wpdb->suppress_errors( $previous_suppression );
                self::log_simple_history(
                    'Simple History integration skipped: missing column "' . $required . '" on ' . $table
                );

                return array();
            }
        }

        $has_context_column = in_array( 'context', $columns, true );

        if ( $has_context_column ) {
            $selectable_columns[] = 'context';
        }

        $column_list = implode(
            ', ',
            array_filter(
                array_map(
                    array( self::class, 'wrap_simple_history_column' ),
                    $selectable_columns
                )
            )
        );

        if ( empty( $column_list ) ) {
            $wpdb->suppress_errors( $previous_suppression );
            self::log_simple_history( 'Simple History integration skipped: no selectable columns available.' );

            return array();
        }

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$column_list} FROM {$table} WHERE action IN (%s, %s, %s) AND date BETWEEN %s AND %s ORDER BY date DESC",
                'plugin_updated',
                'updated',
                'plugin_update',
                $range['from'],
                $range['to']
            ),
            ARRAY_A
        );

        $wpdb->suppress_errors( $previous_suppression );

        if ( null === $events ) {
            self::log_simple_history( 'Failed to query Simple History: ' . $wpdb->last_error );

            return array();
        }

        $history = array();

        foreach ( $events as $event ) {
            $normalized = self::normalize_simple_history_event( $event, $has_context_column );

            if ( ! empty( $normalized ) ) {
                $history[] = $normalized;
            }
        }

        self::log_simple_history( sprintf( 'Loaded %d plugin update rows from Simple History.', count( $history ) ) );

        return $history;
    }

    /**
     * Retrieve available columns from the Simple History table.
     */
    private static function get_simple_history_columns( string $table ): array {
        global $wpdb;

        return array_map( 'strval', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ) );
    }

    /**
     * Build a safe column identifier for SELECT clauses.
     */
    private static function wrap_simple_history_column( string $column ): string {
        $allowed = array( 'date', 'message', 'context' );

        return in_array( $column, $allowed, true ) ? '`' . $column . '`' : '';
    }

    /**
     * Normalize a Simple History row into the SATORI Audit structure.
     */
    private static function normalize_simple_history_event( array $event, bool $has_context ): array {
        $context_data = array();

        if ( $has_context && isset( $event['context'] ) ) {
            $context_data = self::parse_simple_history_context( (string) $event['context'] );
        }

        $message_data = self::parse_simple_history_message( (string) ( $event['message'] ?? '' ) );

        $plugin_name = $context_data['plugin'] ?? $context_data['plugin_name'] ?? '';
        $old_version = $context_data['old_version'] ?? '';
        $new_version = $context_data['new_version'] ?? '';

        if ( empty( $plugin_name ) && ! empty( $context_data['plugin_slug'] ) ) {
            $plugin_name = $context_data['plugin_slug'];
        }

        if ( empty( $plugin_name ) && ! empty( $message_data['plugin'] ) ) {
            $plugin_name = $message_data['plugin'];
        }

        if ( empty( $old_version ) && ! empty( $message_data['old_version'] ) ) {
            $old_version = $message_data['old_version'];
        }

        if ( empty( $new_version ) && ! empty( $message_data['new_version'] ) ) {
            $new_version = $message_data['new_version'];
        }

        $date_value = $event['date'] ?? '';
        $date       = '';

        if ( ! empty( $date_value ) ) {
            $timestamp = strtotime( (string) $date_value );

            if ( false !== $timestamp ) {
                $date = gmdate( 'Y-m-d H:i:s', $timestamp );
            }
        }

        if ( empty( $plugin_name ) ) {
            return array();
        }

        return array(
            'plugin'      => $plugin_name,
            'old_version' => $old_version,
            'new_version' => $new_version,
            'date'        => $date,
        );
    }

    /**
     * Parse a Simple History message column for plugin/version clues.
     */
    private static function parse_simple_history_message( string $message ): array {
        if ( empty( $message ) ) {
            return array();
        }

        $normalized = array(
            'plugin'      => '',
            'old_version' => '',
            'new_version' => '',
        );

        $patterns = array(
            '/Updated plugin\s+\"?(?P<plugin>[^\"]+)\"?\s+from version\s+(?P<old>[\w\.\-]+)\s+to\s+(?P<new>[\w\.\-]+)/i',
            '/Plugin\s+\"?(?P<plugin>[^\"]+)\"?\s+was\s+updated\s+from\s+version\s+(?P<old>[\w\.\-]+)\s+to\s+(?P<new>[\w\.\-]+)/i',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $message, $matches ) ) {
                $normalized['plugin']      = $matches['plugin'] ?? '';
                $normalized['old_version'] = $matches['old'] ?? '';
                $normalized['new_version'] = $matches['new'] ?? '';
                break;
            }
        }

        return $normalized;
    }

    /**
     * Write Simple History-specific log entries.
     */
    private static function log_simple_history( string $message ): void {
        if ( function_exists( 'satori_audit_log' ) ) {
            satori_audit_log( '[Simple History] ' . $message );
        }
    }

    /**
     * Parse the Simple History context payload.
     *
     * @param string $raw Raw context payload.
     */
    private static function parse_simple_history_context( string $raw ): array {
        $decoded = json_decode( $raw, true );

        if ( is_array( $decoded ) ) {
            return self::normalize_context_keys( $decoded );
        }

        $maybe_array = maybe_unserialize( $raw );

        if ( is_array( $maybe_array ) ) {
            return self::normalize_context_keys( $maybe_array );
        }

        return array();
    }

    /**
     * Normalise context keys across possible schemas.
     */
    private static function normalize_context_keys( array $context ): array {
        $normalized = array();

        $normalized['plugin_name'] = $context['plugin_name'] ?? $context['Plugin name'] ?? $context['plugin'] ?? $context['plugin_slug'] ?? '';
        $normalized['plugin_slug'] = $context['plugin_slug'] ?? $context['plugin'] ?? '';
        $normalized['plugin']      = $normalized['plugin_name'] ?: $normalized['plugin_slug'];
        $normalized['old_version'] = $context['version_old'] ?? $context['plugin_version_prev'] ?? $context['old_version'] ?? '';
        $normalized['new_version'] = $context['version_new'] ?? $context['plugin_version'] ?? $context['new_version'] ?? '';

        return $normalized;
    }

    /**
     * Build plugin update history using WordPress data when Simple History is missing.
     */
    private static function build_fallback_history( int $report_id ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $current_plugins  = get_plugins();
        $previous_versions = self::get_previous_versions( $report_id );
        $history           = array();
        $timestamp         = current_time( 'mysql', true );

        foreach ( $current_plugins as $file => $data ) {
            $slug         = sanitize_title( wp_basename( $file ) );
            $plugin_name  = $data['Name'] ?? $slug;
            $new_version  = $data['Version'] ?? '';
            $old_version  = $previous_versions[ $slug ] ?? '';

            $history[] = array(
                'plugin'      => $plugin_name,
                'old_version' => $old_version,
                'new_version' => $new_version,
                'date'        => $timestamp,
            );
        }

        return $history;
    }

    /**
     * Retrieve the most recent known versions from the prior report, if available.
     */
    private static function get_previous_versions( int $report_id ): array {
        if ( $report_id <= 0 ) {
            return array();
        }

        $period = get_post_meta( $report_id, '_satori_audit_period', true );

        if ( empty( $period ) ) {
            return array();
        }

        $previous_period = gmdate( 'Y-m', strtotime( $period . '-01 -1 month' ) );
        $previous_id     = Reports::get_report_id_by_period( $previous_period );

        if ( empty( $previous_id ) ) {
            return array();
        }

        $previous_rows = Reports::get_plugin_rows( (int) $previous_id );
        $versions      = array();

        foreach ( $previous_rows as $row ) {
            if ( empty( $row['plugin_slug'] ) ) {
                continue;
            }

            $versions[ $row['plugin_slug'] ] = $row['version_current'] ?? '';
        }

        return $versions;
    }

    /**
     * Write debug messages when logging is enabled.
     */
    private static function log_debug( string $message ): void {
        if ( function_exists( 'satori_audit_log' ) ) {
            satori_audit_log( $message );
        }
    }

    /**
     * Diff two plugin lists and return change flags.
     *
     * @param array $current Current plugins keyed by slug.
     * @param array $previous Previous plugins keyed by slug.
     *
     * @return array
     */
    public function diff_plugins( array $current, array $previous ): array {
        $diff = [
            'new'       => [],
            'updated'   => [],
            'deleted'   => [],
            'unchanged' => [],
        ];

        foreach ( $current as $slug => $data ) {
            if ( ! isset( $previous[ $slug ] ) ) {
                $diff['new'][ $slug ] = $data;
                continue;
            }

            $previous_version = $previous[ $slug ]['version_current'] ?? '';
            $current_version  = $data['version_current'] ?? '';

            if ( $previous_version !== $current_version ) {
                $diff['updated'][ $slug ] = [
                    'from' => $previous_version,
                    'to'   => $current_version,
                    'data' => $data,
                ];
            } else {
                $diff['unchanged'][ $slug ] = $data;
            }
        }

        foreach ( array_diff_key( $previous, $current ) as $slug => $data ) {
            $diff['deleted'][ $slug ] = $data;
        }

        return $diff;
    }

    /**
     * Refresh plugin inventory rows for a report.
     *
     * @param int $report_id Report post ID.
     * @return void
     */
    public static function refresh_plugins_for_report( int $report_id ): void {
        global $wpdb;

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $table = Tables::table( 'plugins' );

        if ( empty( $table ) ) {
            return;
        }

        $plugins = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );

        $wpdb->delete( $table, array( 'report_id' => $report_id ), array( '%d' ) );

        foreach ( $plugins as $file => $data ) {
            $slug         = sanitize_title( wp_basename( $file ) );
            $description  = isset( $data['Description'] ) ? wp_strip_all_tags( (string) $data['Description'] ) : '';
            $trimmed_desc = wp_trim_words( $description, 40, '…' );
            $is_active    = in_array( $file, $active, true ) ? 1 : 0;

            $wpdb->insert(
                $table,
                array(
                    'report_id'          => $report_id,
                    'plugin_slug'        => $slug,
                    'plugin_name'        => $data['Name'] ?? $slug,
                    'plugin_description' => $trimmed_desc,
                    'plugin_type'        => '',
                    'version_from'       => '',
                    'version_to'         => '',
                    'version_current'    => $data['Version'] ?? '',
                    'is_active'          => $is_active,
                    'status_flag'        => 'unchanged',
                    'price_notes'        => '',
                    'comments'           => '',
                    'last_checked'       => current_time( 'mysql', true ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
            );
        }
    }
}
