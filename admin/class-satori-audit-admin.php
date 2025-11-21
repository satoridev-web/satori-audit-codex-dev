<?php
/**
 * Admin menu registration and screen bootstrap.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin;

use Satori_Audit\Admin\Screens\Satori_Audit_Screen_Archive;
use Satori_Audit\Admin\Screens\Satori_Audit_Screen_Dashboard;
use Satori_Audit\Admin\Screens\Satori_Audit_Screen_Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register admin menus and bootstrap screen controllers.
 */
class Satori_Audit_Admin {
    /**
     * Dashboard screen controller.
     *
     * @var Satori_Audit_Screen_Dashboard
     */
    private Satori_Audit_Screen_Dashboard $dashboard_screen;

    /**
     * Archive screen controller.
     *
     * @var Satori_Audit_Screen_Archive
     */
    private Satori_Audit_Screen_Archive $archive_screen;

    /**
     * Settings screen controller.
     *
     * @var Satori_Audit_Screen_Settings
     */
    private Satori_Audit_Screen_Settings $settings_screen;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->dashboard_screen = new Satori_Audit_Screen_Dashboard();
        $this->archive_screen   = new Satori_Audit_Screen_Archive();
        $this->settings_screen  = new Satori_Audit_Screen_Settings();

        add_action( 'satori_audit_register_admin_screens', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register menu and submenu pages for the plugin.
     */
    public function register_menus(): void {
        $capability = 'manage_options';

        add_menu_page(
            __( 'SATORI Audit', 'satori-audit' ),
            __( 'SATORI Audit', 'satori-audit' ),
            $capability,
            'satori-audit',
            [ $this->dashboard_screen, 'render' ],
            'dashicons-analytics',
            58
        );

        add_submenu_page(
            'satori-audit',
            __( 'Audit Dashboard', 'satori-audit' ),
            __( 'Dashboard', 'satori-audit' ),
            $capability,
            'satori-audit',
            [ $this->dashboard_screen, 'render' ]
        );

        add_submenu_page(
            'satori-audit',
            __( 'Audit Archive', 'satori-audit' ),
            __( 'Archive', 'satori-audit' ),
            $capability,
            'satori-audit-archive',
            [ $this->archive_screen, 'render' ]
        );

        add_submenu_page(
            'satori-audit',
            __( 'Audit Settings', 'satori-audit' ),
            __( 'Settings', 'satori-audit' ),
            $capability,
            'satori-audit-settings',
            [ $this->settings_screen, 'render' ]
        );
    }

    /**
     * Enqueue placeholder assets for admin screens.
     */
    public function enqueue_assets(): void {
        $screen = get_current_screen();

        if ( ! $screen || false === strpos( (string) $screen->base, 'satori-audit' ) ) {
            return;
        }

        wp_enqueue_style(
            'satori-audit-admin',
            SATORI_AUDIT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SATORI_AUDIT_VERSION
        );

        wp_enqueue_script(
            'satori-audit-admin',
            SATORI_AUDIT_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            SATORI_AUDIT_VERSION,
            true
        );
    }
}
