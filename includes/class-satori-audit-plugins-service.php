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
        global $wpdb;

        $table_exists            = self::simple_history_table_exists();
        $simple_history_detected = self::is_simple_history_active() && $table_exists;
        $range                   = self::get_date_range( $report_id );
        $history                 = array();

        if ( $simple_history_detected ) {
            $history = self::load_simple_history_events( $range );

            self::log_debug(
                sprintf(
                    'Simple History detected. Loaded %d update records from %s to %s.',
                    count( $history ),
                    $range['from'],
                    $range['to']
                )
            );

            if ( empty( $history ) ) {
                self::log_debug( 'Simple History returned no plugin updates; falling back to WordPress data.' );
            }
        }

        if ( ! $simple_history_detected || empty( $history ) ) {
            $history = self::build_fallback_history( $report_id );

            self::log_debug(
                sprintf(
                    'Simple History unavailable or empty. Fallback produced %d records.',
                    count( $history )
                )
            );
        }

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
    private static function simple_history_table_exists(): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_history';

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
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
     * Load plugin updates from Simple History events.
     */
    private static function load_simple_history_events( array $range ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_history';

        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, context FROM {$table} WHERE action IN (%s, %s, %s) AND date BETWEEN %s AND %s ORDER BY date DESC",
                'plugin_updated',
                'updated',
                'plugin_update',
                $range['from'],
                $range['to']
            ),
            ARRAY_A
        );

        if ( null === $events ) {
            self::log_debug( 'Failed to query Simple History: ' . $wpdb->last_error );
            return array();
        }

        $history = array();

        foreach ( $events as $event ) {
            $context = self::parse_simple_history_context( (string) $event['context'] );

            if ( empty( $context['plugin'] ) && empty( $context['plugin_name'] ) && empty( $context['plugin_slug'] ) ) {
                continue;
            }

            $history[] = array(
                'plugin'      => $context['plugin_name'] ?? $context['plugin'] ?? $context['plugin_slug'],
                'old_version' => $context['old_version'] ?? $context['version_old'] ?? $context['from_version'] ?? '',
                'new_version' => $context['new_version'] ?? $context['version_new'] ?? $context['to_version'] ?? '',
                'date'        => gmdate( 'Y-m-d H:i:s', strtotime( (string) $event['date'] ) ),
            );
        }

        return $history;
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
