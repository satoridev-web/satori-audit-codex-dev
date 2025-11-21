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

add_action( 'admin_menu', array( self::class, 'register_menus' ) );
add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
}

/**
 * Register menu and submenu pages for the plugin.
 *
 * @return void
 */
public static function register_menus(): void {
$capability = 'manage_options';

add_menu_page(
__( 'SATORI Audit', 'satori-audit' ),
__( 'SATORI Audit', 'satori-audit' ),
$capability,
'satori-audit',
array( Screen_Dashboard::class, 'render' ),
'dashicons-analytics',
58
);

add_submenu_page(
'satori-audit',
__( 'Audit Dashboard', 'satori-audit' ),
__( 'Dashboard', 'satori-audit' ),
$capability,
'satori-audit',
array( Screen_Dashboard::class, 'render' )
);

add_submenu_page(
'satori-audit',
__( 'Audit Archive', 'satori-audit' ),
__( 'Archive', 'satori-audit' ),
$capability,
'satori-audit-archive',
array( Screen_Archive::class, 'render' )
);

add_submenu_page(
'satori-audit',
__( 'Audit Settings', 'satori-audit' ),
__( 'Settings', 'satori-audit' ),
$capability,
'satori-audit-settings',
array( Screen_Settings::class, 'render' )
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
