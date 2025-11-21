<?php
/**
 * Plugin Name: SATORI – Audit & Reports
 * Plugin URI:  https://satori.com.au/
 * Description: SATORI Audit plugin scaffolding – built via Codex. Generates monthly service reports for client sites.
 * Version:     0.0.1-dev
 * Author:      Satori Graphics Pty Ltd
 * Author URI:  https://satori.com.au/
 * Text Domain: satori-audit
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants.
 */
define( 'SATORI_AUDIT_VERSION', '0.0.1-dev' );
define( 'SATORI_AUDIT_MIN_PHP', '8.0' );
define( 'SATORI_AUDIT_MIN_WP', '6.1' );
define( 'SATORI_AUDIT_PLUGIN_FILE', __FILE__ );
define( 'SATORI_AUDIT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SATORI_AUDIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SATORI_AUDIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SATORI_AUDIT_NAMESPACE', 'Satori_Audit\\' );

/**
 * Autoloader for plugin classes using the Satori_Audit namespace.
 *
 * Converts class names to lowercase, hyphenated filenames with a `class-` prefix
 * and maps namespaces to the `admin/` and `includes/` directories.
 *
 * @param string $class Fully-qualified class name.
 */
function satori_audit_autoload( string $class ): void {
    $prefix = SATORI_AUDIT_NAMESPACE;

    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    $relative_class = substr( $class, strlen( $prefix ) );
    $parts          = explode( '\\', $relative_class );
    $file_name_part = array_pop( $parts );
    $path_parts     = array_map(
        static function ( string $segment ): string {
            return str_replace( '_', '-', strtolower( $segment ) );
        },
        $parts
    );

    $file_name = 'class-' . str_replace( '_', '-', strtolower( $file_name_part ) ) . '.php';
    $sub_path  = implode( '/', $path_parts );
    $locations = [ SATORI_AUDIT_PLUGIN_DIR . 'includes/', SATORI_AUDIT_PLUGIN_DIR . 'admin/' ];

    foreach ( $locations as $base ) {
        $path = $base . ( $sub_path ? $sub_path . '/' : '' ) . $file_name;

        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
}

spl_autoload_register( 'satori_audit_autoload' );

register_activation_hook(
    SATORI_AUDIT_PLUGIN_FILE,
    static function (): void {
        if ( class_exists( '\\Satori_Audit\\Includes\\Satori_Audit_Plugin' ) ) {
            \Satori_Audit\Includes\Satori_Audit_Plugin::activate();
        }
    }
);

/**
 * Add an admin notice for environment issues.
 *
 * @param string $message Message to display.
 */
function satori_audit_admin_notice( string $message ): void {
    if ( ! function_exists( 'add_action' ) ) {
        return;
    }

    add_action(
        'admin_notices',
        static function () use ( $message ): void {
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
        }
    );
}

/**
 * Determine if the current environment meets plugin requirements.
 *
 * @return bool
 */
function satori_audit_is_compatible(): bool {
    if ( version_compare( PHP_VERSION, SATORI_AUDIT_MIN_PHP, '<' ) ) {
        satori_audit_admin_notice( sprintf( 'SATORI Audit requires PHP %s or newer.', SATORI_AUDIT_MIN_PHP ) );
        return false;
    }

    global $wp_version;
    $wordpress_version = $wp_version ?? ( function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '0' );

    if ( version_compare( $wordpress_version, SATORI_AUDIT_MIN_WP, '<' ) ) {
        satori_audit_admin_notice( sprintf( 'SATORI Audit requires WordPress %s or newer.', SATORI_AUDIT_MIN_WP ) );
        return false;
    }

    return true;
}

/**
 * Boot the main plugin class.
 */
function satori_audit_boot(): void {
    if ( ! class_exists( '\\Satori_Audit\\Includes\\Satori_Audit_Plugin' ) ) {
        satori_audit_admin_notice( 'SATORI Audit could not locate the plugin bootstrap class.' );
        return;
    }

    \Satori_Audit\Includes\Satori_Audit_Plugin::instance();
}

if ( satori_audit_is_compatible() ) {
    add_action( 'plugins_loaded', 'satori_audit_boot' );
}
