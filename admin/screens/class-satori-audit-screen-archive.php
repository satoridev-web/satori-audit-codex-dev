<?php
/**
 * Archive admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Render the Archive page for SATORI Audit.
 */
class Screen_Archive {
/**
 * Display archive content.
 *
 * @return void
 */
public static function render(): void {
echo '<div class="wrap satori-audit-wrap">';
echo '<h1>' . esc_html__( 'SATORI Audit – Archive', 'satori-audit' ) . '</h1>';
echo '<p>' . esc_html__( 'Archive listings will appear here once reports are generated.', 'satori-audit' ) . '</p>';
echo '</div>';
}
}
