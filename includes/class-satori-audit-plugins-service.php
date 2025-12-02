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

        if ( empty( $settings['track_update_history_internal'] ) ) {
            self::log_debug( 'Plugin update history disabled via settings.' );

            return array();
        }

        $range = self::get_date_range( $report_id );

        Update_Storage::maybe_migrate_recent_history();

        $rows = Update_Storage::get_updates_between( $range['from'], $range['to'] );

        if ( empty( $rows ) ) {
            $rows = self::sync_recent_updates( $report_id, $range );
        }

        $history = array_map(
            static function ( array $row ): array {
                return array(
                    'plugin'      => $row['plugin_name'] ?? '',
                    'old_version' => $row['previous_version'] ?? '',
                    'new_version' => $row['new_version'] ?? '',
                    'date'        => $row['updated_on'] ?? '',
                );
            },
            $rows
        );

        self::log_debug(
            sprintf(
                'Compiled plugin update history for report %d using internal storage. Total records: %d.',
                $report_id,
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
            $slug = self::normalise_slug( $file );

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
    private static function normalise_slug( string $plugin_file ): string {
        $parts = explode( '/', $plugin_file );

        return sanitize_title( $parts[0] ?? $plugin_file );
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
     * Derive recent updates by diffing current plugins against the previous report.
     */
    private static function sync_recent_updates( int $report_id, array $range ): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $current_plugins   = get_plugins();
        $previous_versions = self::get_previous_versions( $report_id );
        $timestamp         = $range['to'];

        foreach ( $current_plugins as $file => $data ) {
            $slug        = sanitize_title( wp_basename( (string) $file ) );
            $plugin_name = (string) ( $data['Name'] ?? $slug );
            $new_version = (string) ( $data['Version'] ?? '' );
            $old_version = (string) ( $previous_versions[ $slug ] ?? '' );

            if ( empty( $old_version ) || $old_version === $new_version ) {
                continue;
            }

            Update_Storage::record_update(
                array(
                    'plugin_slug'      => $slug,
                    'plugin_name'      => $plugin_name,
                    'previous_version' => $old_version,
                    'new_version'      => $new_version,
                    'updated_on'       => $timestamp,
                    'source'           => 'auto',
                )
            );
        }

        return Update_Storage::get_updates_between( $range['from'], $range['to'] );
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
