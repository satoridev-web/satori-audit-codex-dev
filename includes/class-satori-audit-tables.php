<?php
/**
 * Database table scaffolding.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle database table registration and activation hooks.
 */
class Tables {
    /**
     * Database schema version.
     */
    public const DB_VERSION = '0.2.0';

    /**
     * Map custom table names onto the $wpdb instance.
     *
     * @return void
     */
    public static function register_table_names(): void {
        global $wpdb;

        $wpdb->satori_audit_plugins  = $wpdb->prefix . 'satori_audit_plugins';
        $wpdb->satori_audit_security = $wpdb->prefix . 'satori_audit_security';
        $wpdb->satori_audit_updates  = $wpdb->prefix . 'satori_audit_updates';
    }

    /**
     * Activation routine to create or update database tables.
     *
     * @return void
     */
    public static function install(): void {
        self::register_table_names();
        self::maybe_create_tables();
        update_option( 'satori_audit_db_version', self::DB_VERSION );
    }

    /**
     * Run upgrades if the stored version is behind.
     *
     * @return void
     */
    public static function maybe_upgrade(): void {
        self::register_table_names();

        $stored_version = get_option( 'satori_audit_db_version', '' );

        if ( version_compare( (string) $stored_version, self::DB_VERSION, '>=' ) ) {
            return;
        }

        self::maybe_create_tables();
        update_option( 'satori_audit_db_version', self::DB_VERSION );
    }

    /**
     * Helper returning custom table name by key.
     *
     * @param string $key Table key.
     *
     * @return string
     */
    public static function table( string $key ): string {
        global $wpdb;

        return match ( $key ) {
            'plugins'  => $wpdb->prefix . 'satori_audit_plugins',
            'security' => $wpdb->prefix . 'satori_audit_security',
            'updates'  => $wpdb->prefix . 'satori_audit_updates',
            default    => '',
        };
    }

    /**
     * dbDelta-based table creation.
     *
     * @return void
     */
    private static function maybe_create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $plugin_table_sql  = "CREATE TABLE {$wpdb->prefix}satori_audit_plugins (\n" .
            ' id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
            ' report_id bigint(20) unsigned NOT NULL,' .
            ' plugin_slug varchar(191) NOT NULL,' .
            ' plugin_name varchar(191) NOT NULL,' .
            ' plugin_description text NULL,' .
            ' plugin_type varchar(50) NULL,' .
            ' version_from varchar(50) NULL,' .
            ' version_to varchar(50) NULL,' .
            ' version_current varchar(50) NULL,' .
            ' is_active tinyint(1) DEFAULT 0,' .
            ' status_flag varchar(20) NOT NULL,' .
            ' price_notes text NULL,' .
            ' comments text NULL,' .
            ' last_checked datetime NULL,' .
            ' PRIMARY KEY  (id),' .
            ' KEY report_id (report_id),' .
            ' KEY plugin_slug (plugin_slug)' .
            " ) {$charset_collate};";

        $security_table_sql = "CREATE TABLE {$wpdb->prefix}satori_audit_security (\n" .
            ' id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
            ' report_id bigint(20) unsigned NOT NULL,' .
            ' vulnerability_type varchar(191) NULL,' .
            ' description text NULL,' .
            ' cvss_score varchar(50) NULL,' .
            ' severity varchar(50) NULL,' .
            ' attack_report text NULL,' .
            ' solution text NULL,' .
            ' comments text NULL,' .
            ' action_required tinyint(1) DEFAULT 0,' .
            ' PRIMARY KEY  (id),' .
            ' KEY report_id (report_id)' .
            " ) {$charset_collate};";

        $updates_table_sql = "CREATE TABLE {$wpdb->prefix}satori_audit_updates (\n" .
            ' id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
            ' plugin_slug varchar(190) NOT NULL,' .
            ' plugin_name varchar(255) NOT NULL,' .
            ' previous_version varchar(50) NULL,' .
            ' new_version varchar(50) NULL,' .
            ' updated_on datetime NOT NULL,' .
            " source varchar(20) NOT NULL DEFAULT 'auto'," .
            ' created_at datetime NOT NULL,' .
            ' updated_at datetime NOT NULL,' .
            ' PRIMARY KEY  (id),' .
            ' KEY plugin_slug (plugin_slug),' .
            ' KEY updated_on (updated_on)' .
            " ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( array( $plugin_table_sql, $security_table_sql, $updates_table_sql ) );
    }
}
