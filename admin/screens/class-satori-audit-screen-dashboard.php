<?php
/**
 * Dashboard admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Render the Dashboard page for SATORI Audit.
 */
class Screen_Dashboard {
/**
 * Output the dashboard screen.
 *
 * @return void
 */
public static function render(): void {
echo '<div class="wrap satori-audit-wrap">';
echo '<h1>' . esc_html__( 'SATORI Audit – Dashboard', 'satori-audit' ) . '</h1>';
echo '<p>' . esc_html__( 'Welcome to the SATORI Audit dashboard. Reporting tools will appear here.', 'satori-audit' ) . '</p>';
echo '<div class="satori-audit-panel">';
echo '<p>' . esc_html__( 'Use the Archive and Settings screens to manage reports and configuration.', 'satori-audit' ) . '</p>';
echo '</div>';
echo '</div>';
}
}
