<?php
/**
 * Database table scaffolding.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle database table registration and activation hooks.
 */
class Satori_Audit_Tables {
    /**
     * Database schema version.
     */
    public const DB_VERSION = '0.0.1';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'satori_audit_register_tables', [ $this, 'register_table_names' ] );
    }

    /**
     * Map custom table names onto the $wpdb instance.
     */
    public function register_table_names(): void {
        global $wpdb;

        $wpdb->satori_audit_plugins  = $wpdb->prefix . 'satori_audit_plugins';
        $wpdb->satori_audit_security = $wpdb->prefix . 'satori_audit_security';
    }

    /**
     * Activation routine to create or update database tables.
     */
    public static function activate(): void {
        self::maybe_create_tables();
        update_option( 'satori_audit_db_version', self::DB_VERSION );
    }

    /**
     * Placeholder for dbDelta-based table creation.
     */
    private static function maybe_create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $plugin_table_sql  = "CREATE TABLE {$wpdb->prefix}satori_audit_plugins (\n" .
            'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
            'report_id bigint(20) unsigned NOT NULL,' .
            'plugin_slug varchar(191) NOT NULL,' .
            'plugin_name varchar(191) NOT NULL,' .
            'plugin_description text NULL,' .
            'plugin_type varchar(50) NULL,' .
            'version_from varchar(50) NULL,' .
            'version_to varchar(50) NULL,' .
            'version_current varchar(50) NULL,' .
            'is_active tinyint(1) DEFAULT 0,' .
            'status_flag varchar(20) NOT NULL,' .
            'price_notes text NULL,' .
            'comments text NULL,' .
            'last_checked datetime NULL,' .
            'PRIMARY KEY  (id),' .
            'KEY report_id (report_id),' .
            'KEY plugin_slug (plugin_slug)' .
            " ) {$charset_collate};";

        $security_table_sql = "CREATE TABLE {$wpdb->prefix}satori_audit_security (\n" .
            'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,' .
            'report_id bigint(20) unsigned NOT NULL,' .
            'vulnerability_type varchar(191) NULL,' .
            'description text NULL,' .
            'cvss_score varchar(50) NULL,' .
            'severity varchar(50) NULL,' .
            'attack_report text NULL,' .
            'solution text NULL,' .
            'comments text NULL,' .
            'action_required tinyint(1) DEFAULT 0,' .
            'PRIMARY KEY  (id),' .
            'KEY report_id (report_id)' .
            " ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( [ $plugin_table_sql, $security_table_sql ] );
    }
}
