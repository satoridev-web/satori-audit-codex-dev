<?php
/**
 * Archive admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Archive page for SATORI Audit.
 */
class Satori_Audit_Screen_Archive {
    /**
     * Display placeholder archive content.
     */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Archive', 'satori-audit' ) . '</h1>';
        echo '<p>' . esc_html__( 'A sortable archive of monthly reports will appear here.', 'satori-audit' ) . '</p>';
        echo '<div class="satori-audit-placeholder">' . esc_html__( 'Archive list table coming soon.', 'satori-audit' ) . '</div>';
        echo '</div>';
    }
}
