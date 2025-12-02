<?php
/**
 * Internal storage for plugin update history.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide CRUD helpers for plugin update history rows.
 */
class Update_Storage {
    /**
     * Option key used to gate the one-time migration.
     */
    private const MIGRATION_OPTION = 'satori_audit_migration_done';

    /**
     * Hook runtime events.
     */
    public static function init(): void {
        add_action( 'init', array( self::class, 'maybe_migrate_recent_history' ) );
        add_action( 'upgrader_process_complete', array( self::class, 'capture_upgrader_event' ), 10, 2 );
    }

    /**
     * Return the fully-qualified updates table name.
     */
    public static function table(): string {
        Tables::register_table_names();

        return Tables::table( 'updates' );
    }

    /**
     * Insert a plugin update record when tracking is enabled.
     *
     * @param array{plugin_slug:string,plugin_name:string,previous_version:string,new_version:string,updated_on?:string,source?:string} $data Update payload.
     */
    public static function record_update( array $data ): bool {
        global $wpdb;

        $table = self::table();

        if ( empty( $table ) || ! self::table_exists( $table ) ) {
            return false;
        }

        $defaults = array(
            'updated_on' => current_time( 'mysql', true ),
            'source'     => 'auto',
        );

        $payload = array_merge( $defaults, $data );

        $payload['updated_on'] = self::normalise_datetime( (string) $payload['updated_on'] );
        $now                   = current_time( 'mysql', true );

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE plugin_slug = %s AND new_version = %s AND updated_on = %s",
                $payload['plugin_slug'],
                $payload['new_version'],
                $payload['updated_on']
            )
        );

        if ( ! empty( $existing ) ) {
            return true;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'plugin_slug'      => $payload['plugin_slug'],
                'plugin_name'      => $payload['plugin_name'],
                'previous_version' => $payload['previous_version'],
                'new_version'      => $payload['new_version'],
                'updated_on'       => $payload['updated_on'],
                'source'           => $payload['source'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false !== $inserted ) {
            self::log( sprintf( 'Recorded plugin update for %s (%s → %s).', $payload['plugin_slug'], $payload['previous_version'], $payload['new_version'] ) );
        }

        return false !== $inserted;
    }

    /**
     * Retrieve updates between two datetimes (inclusive).
     */
    public static function get_updates_between( string $start, string $end ): array {
        global $wpdb;

        $table = self::table();

        if ( empty( $table ) || ! self::table_exists( $table ) ) {
            return array();
        }

        $start = self::normalise_datetime( $start );
        $end   = self::normalise_datetime( $end );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE updated_on BETWEEN %s AND %s ORDER BY updated_on DESC, id DESC",
                $start,
                $end
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return array();
        }

        return array_map( 'wp_parse_args', $rows );
    }

    /**
     * Retrieve updates for a given YYYY-MM period.
     */
    public static function get_updates_for_month( string $month ): array {
        $start = gmdate( 'Y-m-01 00:00:00', strtotime( $month . '-01 00:00:00' ) );
        $end   = gmdate( 'Y-m-t 23:59:59', strtotime( $month . '-01 00:00:00' ) );

        return self::get_updates_between( $start, $end );
    }

    /**
     * Return the most recent update row for a slug.
     */
    public static function get_latest_for_plugin( string $plugin_slug ): array {
        global $wpdb;

        $table = self::table();

        if ( empty( $table ) || ! self::table_exists( $table ) ) {
            return array();
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE plugin_slug = %s ORDER BY updated_on DESC, id DESC LIMIT 1",
                $plugin_slug
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $row : array();
    }

    /**
     * Run a one-time import of recent plugin updates from WordPress metadata.
     */
    public static function maybe_migrate_recent_history(): void {
        if ( get_option( self::MIGRATION_OPTION, false ) ) {
            return;
        }

        self::import_recent_updates();
        update_option( self::MIGRATION_OPTION, true );
    }

    /**
     * Capture plugin updates performed through the WordPress upgrader.
     *
     * @param \WP_Upgrader $upgrader   Upgrader instance.
     * @param array         $hook_extra Context about the update run.
     */
    public static function capture_upgrader_event( $upgrader, array $hook_extra ): void {
        $settings = Settings::get_settings();

        if ( empty( $settings['track_update_history_internal'] ) ) {
            return;
        }

        if ( ( $hook_extra['type'] ?? '' ) !== 'plugin' || empty( $hook_extra['plugins'] ) ) {
            return;
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $now     = current_time( 'mysql', true );

        foreach ( (array) $hook_extra['plugins'] as $plugin_file ) {
            if ( ! isset( $plugins[ $plugin_file ] ) ) {
                continue;
            }

            $slug         = sanitize_title( wp_basename( (string) $plugin_file ) );
            $plugin_data  = $plugins[ $plugin_file ];
            $new_version  = (string) ( $plugin_data['Version'] ?? '' );
            $plugin_name  = (string) ( $plugin_data['Name'] ?? $slug );
            $latest_entry = self::get_latest_for_plugin( $slug );
            $old_version  = $latest_entry['new_version'] ?? (string) ( $plugin_data['Version'] ?? '' );

            self::record_update(
                array(
                    'plugin_slug'      => $slug,
                    'plugin_name'      => $plugin_name,
                    'previous_version' => $old_version,
                    'new_version'      => $new_version,
                    'updated_on'       => $now,
                    'source'           => 'auto',
                )
            );
        }
    }

    /**
     * Perform a best-effort import of recent update data from WordPress options.
     */
    private static function import_recent_updates(): void {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins            = get_plugins();
        $update_plugins     = get_option( 'update_plugins', array() );
        $auto_update_info   = get_option( 'auto_plugin_update_info', array() );
        $cutoff_timestamp   = strtotime( '-30 days', current_time( 'timestamp', true ) );
        $update_timestamps  = self::extract_auto_update_timestamps( $auto_update_info, $cutoff_timestamp );
        $last_checked_value = isset( $update_plugins['last_checked'] ) ? (int) $update_plugins['last_checked'] : 0;

        foreach ( $plugins as $file => $data ) {
            $slug        = sanitize_title( wp_basename( (string) $file ) );
            $plugin_name = (string) ( $data['Name'] ?? $slug );
            $new_version = (string) ( $data['Version'] ?? '' );

            $timestamps = $update_timestamps[ $slug ] ?? array();

            if ( empty( $timestamps ) && $last_checked_value > $cutoff_timestamp ) {
                $timestamps[] = $last_checked_value;
            }

            foreach ( $timestamps as $timestamp ) {
                if ( $timestamp < $cutoff_timestamp ) {
                    continue;
                }

                $updated_on = gmdate( 'Y-m-d H:i:s', $timestamp );

                self::record_update(
                    array(
                        'plugin_slug'      => $slug,
                        'plugin_name'      => $plugin_name,
                        'previous_version' => '',
                        'new_version'      => $new_version,
                        'updated_on'       => $updated_on,
                        'source'           => 'import',
                    )
                );
            }
        }
    }

    /**
     * Extract timestamps from auto-update metadata where possible.
     *
     * @param array $auto_update_info Option payload.
     * @param int   $cutoff_timestamp Minimum timestamp to consider.
     *
     * @return array<string,array<int>>
     */
    private static function extract_auto_update_timestamps( array $auto_update_info, int $cutoff_timestamp ): array {
        $events = array();

        $sections = array( 'auto_updates', 'successful', 'failed', 'plugins', 'updates' );

        foreach ( $sections as $key ) {
            if ( empty( $auto_update_info[ $key ] ) || ! is_array( $auto_update_info[ $key ] ) ) {
                continue;
            }

            foreach ( $auto_update_info[ $key ] as $plugin_key => $payload ) {
                if ( is_array( $payload ) ) {
                    $slug      = sanitize_title( wp_basename( (string) ( $payload['plugin'] ?? $plugin_key ) ) );
                    $timestamp = (int) ( $payload['timestamp'] ?? $payload['time'] ?? $payload['when'] ?? $payload['last_checked'] ?? 0 );

                    if ( $timestamp > $cutoff_timestamp ) {
                        $events[ $slug ][] = $timestamp;
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Verify the updates table exists before querying.
     */
    private static function table_exists( string $table ): bool {
        global $wpdb;

        $table_name = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

        return $table_name === $table;
    }

    /**
     * Normalise a datetime string for storage.
     */
    private static function normalise_datetime( string $value ): string {
        if ( empty( $value ) ) {
            return current_time( 'mysql', true );
        }

        $timestamp = strtotime( $value );

        if ( false === $timestamp ) {
            return current_time( 'mysql', true );
        }

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Log helper for update storage.
     */
    private static function log( string $message ): void {
        if ( function_exists( 'satori_audit_log' ) ) {
            satori_audit_log( '[Updates] ' . $message );
        }
    }
}
