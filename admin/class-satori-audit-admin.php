<?php
/**
 * Admin menu registration and screen bootstrap.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

use Satori_Audit\Screen_Archive;
use Satori_Audit\Screen_Dashboard;
use Satori_Audit\Screen_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menus and bootstrap screen controllers.
 */
class Admin {

	/**
	 * Flag to avoid double boot.
	 *
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Initialise admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( true === self::$booted ) {
            return;
        }

		self::$booted = true;

		/* -------------------------------------------------
		 * Screen bootstrap
		 * -------------------------------------------------*/

		// Ensure the Settings screen registers its sections and fields.
		// This hooks into admin_init internally.
		Screen_Settings::init();

		// If you later add ::init() methods for Dashboard / Archive,
		// you can bootstrap them here in the same way.

		/* -------------------------------------------------
		 * Hooks
		 * -------------------------------------------------*/
		add_action( 'admin_menu', array( self::class, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
	}

        /**
         * Register menu and submenu pages for the plugin.
         *
         * @return void
         */
        public static function register_menus(): void {
                /* -------------------------------------------------
                 * Determine capability from settings
                 * -------------------------------------------------*/
                $settings     = Screen_Settings::get_settings();
                $capabilities = Screen_Settings::get_capabilities();
                $hide_menu    = ! empty( $settings['hide_menu_from_non_admin'] );

                $manage_capability = $capabilities['manage'];
                $view_capability   = $capabilities['view'];
                $menu_capability   = $hide_menu ? $manage_capability : $view_capability;

                /* -------------------------------------------------
                 * Top-level menu: Dashboard
                 * -------------------------------------------------*/
                add_menu_page(
                        __( 'SATORI Audit', 'satori-audit' ),
                        __( 'SATORI Audit', 'satori-audit' ),
                        $menu_capability,
                        'satori-audit',
                        array( Screen_Dashboard::class, 'render' ),
                        'dashicons-analytics',
                        58
                );

		/* -------------------------------------------------
                 * Submenu: Dashboard (default)
                 * -------------------------------------------------*/
                add_submenu_page(
                        'satori-audit',
                        __( 'Audit Dashboard', 'satori-audit' ),
                        __( 'Dashboard', 'satori-audit' ),
                        $menu_capability,
                        'satori-audit',
                        array( Screen_Dashboard::class, 'render' )
                );

                /* -------------------------------------------------
                 * Submenu: Archive
                 * -------------------------------------------------*/
                add_submenu_page(
                        'satori-audit',
                        __( 'Audit Archive', 'satori-audit' ),
                        __( 'Archive', 'satori-audit' ),
                        $menu_capability,
                        'satori-audit-archive',
                        array( Screen_Archive::class, 'render' )
                );

                /* -------------------------------------------------
                 * Submenu: Settings
                 * -------------------------------------------------*/
                add_submenu_page(
                        'satori-audit',
                        __( 'Audit Settings', 'satori-audit' ),
                        __( 'Settings', 'satori-audit' ),
                        $manage_capability,
                        Screen_Settings::PAGE_SLUG,
                        array( Screen_Settings::class, 'render_page' )
                );

                Screen_Settings::log_debug(
                        sprintf(
                                'Menus registered with hide_menu_from_non_admin=%d (top-level capability: %s, settings capability: %s, view capability: %s).',
                                $hide_menu ? 1 : 0,
                                $menu_capability,
                                $manage_capability,
                                $view_capability
                        ),
                        $settings
                );
        }

	/**
	 * Enqueue assets for admin screens.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( (string) $screen->base, 'satori-audit' ) ) {
			return;
		}

		wp_enqueue_style(
			'satori-audit-admin',
			sprintf( '%1$sassets/css/admin.css', SATORI_AUDIT_URL ),
			array(),
			SATORI_AUDIT_VERSION
		);

		wp_enqueue_script(
			'satori-audit-admin',
			sprintf( '%1$sassets/js/admin.js', SATORI_AUDIT_URL ),
			array( 'jquery' ),
			SATORI_AUDIT_VERSION,
			true
		);
	}
}
