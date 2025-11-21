<?php
/**
 * Plugin Name:       SATORI – Audit & Reports
 * Plugin URI:        https://satori.com.au/
 * Description:       Generates monthly audit reports for WordPress sites, including plugin inventory, changes, and service logs.
 * Version:           0.1.0
 * Author:            Satori Graphics Pty Ltd
 * Author URI:        https://satori.com.au/
 * Text Domain:       satori-audit
 * Domain Path:       /languages
 *
 * @package Satori_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access.
}

/* -------------------------------------------------
 * Core plugin constants
 * -------------------------------------------------*/

if ( ! defined( 'SATORI_AUDIT_VERSION' ) ) {
	define( 'SATORI_AUDIT_VERSION', '0.1.0' );
}

if ( ! defined( 'SATORI_AUDIT_FILE' ) ) {
	define( 'SATORI_AUDIT_FILE', __FILE__ );
}

if ( ! defined( 'SATORI_AUDIT_PATH' ) ) {
	define( 'SATORI_AUDIT_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SATORI_AUDIT_URL' ) ) {
	define( 'SATORI_AUDIT_URL', plugin_dir_url( __FILE__ ) );
}

/* -------------------------------------------------
 * PSR-4–style autoloader
 * -------------------------------------------------
 *
 * Maps the Satori_Audit\* namespace to:
 *  - /includes/class-satori-audit-*.php
 *  - /admin/class-satori-audit-*.php
 *  - /admin/screens/class-satori-audit-*.php
 *
 * Example:
 *  Satori_Audit\Plugin           → includes/class-satori-audit-plugin.php
 *  Satori_Audit\Cpt              → includes/class-satori-audit-cpt.php
 *  Satori_Audit\Tables           → includes/class-satori-audit-tables.php
 *  Satori_Audit\Admin            → admin/class-satori-audit-admin.php
 *  Satori_Audit\Screen_Dashboard → admin/screens/class-satori-audit-screen-dashboard.php
 */

spl_autoload_register(
	/**
	 * Autoload Satori_Audit classes.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	function ( $class ) {

		$prefix = 'Satori_Audit\\';

		// Not our namespace, bail early.
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		// Strip namespace prefix.
		$relative = str_replace( $prefix, '', $class );

		// Convert namespace separators to underscores.
		$relative = str_replace( '\\', '_', $relative );

		// Convert CamelCase / underscores to lowercase-hyphenated file suffix.
		//   Plugin           → plugin
		//   Cpt              → cpt
		//   Plugins_Service  → plugins-service
		//   Screen_Dashboard → screen-dashboard
		$file_suffix = strtolower( str_replace( '_', '-', $relative ) );

		$files_to_try = array(
			SATORI_AUDIT_PATH . 'includes/class-satori-audit-' . $file_suffix . '.php',
			SATORI_AUDIT_PATH . 'admin/class-satori-audit-' . $file_suffix . '.php',
			SATORI_AUDIT_PATH . 'admin/screens/class-satori-audit-' . $file_suffix . '.php',
		);

		foreach ( $files_to_try as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

/* -------------------------------------------------
 * Activation hook
 * -------------------------------------------------*/

/**
 * Run tasks on plugin activation.
 *
 * This is intentionally small; the heavy lifting lives
 * in Satori_Audit\Plugin::activate() if present.
 */
function satori_audit_activate() {
	if ( class_exists( 'Satori_Audit\\Plugin' ) && method_exists( 'Satori_Audit\\Plugin', 'activate' ) ) {
		\Satori_Audit\Plugin::activate();
	}
}

register_activation_hook( __FILE__, 'satori_audit_activate' );

/* -------------------------------------------------
 * Bootstrap plugin on plugins_loaded
 * -------------------------------------------------*/

add_action(
	'plugins_loaded',
	static function () {
		// Load translations.
		load_plugin_textdomain(
			'satori-audit',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		// Ensure the core plugin class exists.
		if ( class_exists( 'Satori_Audit\\Plugin' ) ) {
			\Satori_Audit\Plugin::init();
			return;
		}

		// Fallback admin notice if the bootstrap class cannot be found.
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__(
						'SATORI Audit could not locate the plugin bootstrap class (Satori_Audit\\Plugin). Please check the plugin files.',
						'satori-audit'
					);
					echo '</p></div>';
				}
			);
		}
	}
);
