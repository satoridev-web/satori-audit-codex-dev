<?php
/**
 * Settings admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Render the Settings page for SATORI Audit.
 */
class Screen_Settings {
/**
 * Display settings content.
 *
 * @return void
 */
public static function render(): void {
echo '<div class="wrap satori-audit-wrap">';
echo '<h1>' . esc_html__( 'SATORI Audit – Settings', 'satori-audit' ) . '</h1>';
echo '<p>' . esc_html__( 'Configure SATORI Audit options here.', 'satori-audit' ) . '</p>';
echo '</div>';
}
}
