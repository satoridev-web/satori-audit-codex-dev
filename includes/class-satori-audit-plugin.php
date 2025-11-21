<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

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
     * Hook into WordPress on construction.
     */
    private function __construct() {
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
}
