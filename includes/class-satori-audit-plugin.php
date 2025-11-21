<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

use Satori_Audit\Admin\Satori_Audit_Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Primary plugin controller handling hooks for CPTs, tables, admin screens, and settings.
 */
class Satori_Audit_Plugin {
    /**
     * Singleton instance.
     *
     * @var Satori_Audit_Plugin|null
     */
    private static ?Satori_Audit_Plugin $instance = null;

    /**
     * Retrieve the singleton instance.
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Public init entry point for Plugin::init() requirement.
     */
    public static function init(): self {
        return self::instance();
    }

    /**
     * Hook into WordPress on construction.
     */
    private function __construct() {
        new Satori_Audit_Cpt();
        new Satori_Audit_Tables();
        new Satori_Audit_Admin();
        new Satori_Audit_Reports();
        new Satori_Audit_Plugins_Service();
        new Satori_Audit_Automation();

        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_custom_post_types' ] );
        add_action( 'init', [ $this, 'register_database_tables' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_screens' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Load translations for the plugin.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'satori-audit', false, dirname( SATORI_AUDIT_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Register custom post types related to audit data.
     */
    public function register_custom_post_types(): void {
        do_action( 'satori_audit_load_custom_post_types' );
    }

    /**
     * Register or bootstrap any database tables used by the plugin.
     */
    public function register_database_tables(): void {
        do_action( 'satori_audit_register_tables' );
    }

    /**
     * Register admin menus and screens.
     */
    public function register_admin_screens(): void {
        do_action( 'satori_audit_register_admin_screens' );
    }

    /**
     * Register settings and related integrations.
     */
    public function register_settings(): void {
        do_action( 'satori_audit_register_settings' );
    }

    /**
     * Activation callback.
     */
    public static function activate(): void {
        Satori_Audit_Tables::activate();
        update_option( 'satori_audit_settings', self::get_default_settings() );
    }

    /**
     * Retrieve stored settings with defaults.
     *
     * @return array
     */
    public static function get_settings(): array {
        $saved    = get_option( 'satori_audit_settings', [] );
        $defaults = self::get_default_settings();

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Default settings structure covering all tabs.
     *
     * @return array
     */
    public static function get_default_settings(): array {
        return [
            // Service Details.
            'client_name'        => '',
            'site_name'          => '',
            'site_url'           => '',
            'managed_by'         => '',
            'start_date'         => '',
            'service_date'       => '',
            'technician_name'    => '',
            'technician_email'   => '',
            'technician_phone'   => '',
            'logo_id'            => '',
            // Notifications.
            'from_email'         => get_bloginfo( 'admin_email' ),
            'default_recipients' => '',
            'webhook_url'        => '',
            'suppress_wp_emails' => 0,
            // Safelist.
            'enforce_safelist'   => 0,
            'safelist'           => '',
            // Access Control.
            'cap_manage'         => 'manage_options',
            'cap_export'         => 'manage_options',
            'cap_settings'       => 'manage_options',
            'admin_email'        => get_bloginfo( 'admin_email' ),
            // Automation.
            'enable_cron'        => 0,
            'cron_day'           => '1',
            'cron_time'          => '03:00',
            'retain_months'      => '0',
            // Display & Output.
            'show_security'      => 1,
            'show_known_issues'  => 1,
            'pdf_page_size'      => 'A4',
            'pdf_orientation'    => 'portrait',
            'footer_text'        => '',
            // PDF Diagnostics.
            'pdf_engine'         => '',
            'pdf_path'           => '',
        ];
    }
}
