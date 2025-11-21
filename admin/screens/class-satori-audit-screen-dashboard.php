<?php
/**
 * Dashboard admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Dashboard page for SATORI Audit.
 */
class Satori_Audit_Screen_Dashboard {
    /**
     * Display placeholder dashboard content.
     */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Dashboard', 'satori-audit' ) . '</h1>';
        echo '<p>' . esc_html__( 'This screen will show the current period report, generation controls, and quick exports.', 'satori-audit' ) . '</p>';
        echo '<div class="satori-audit-placeholder">' . esc_html__( 'Dashboard widgets and report preview coming soon.', 'satori-audit' ) . '</div>';
        echo '</div>';
    }
}
