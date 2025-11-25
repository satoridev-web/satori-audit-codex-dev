<?php
/**
 * Admin report preview template.
 *
 * @package Satori_Audit
 */

$report_id = $report_id ?? ( $selected_report_id ?? 0 );

if ( ! empty( $export_url ) ) {
        echo '<p class="satori-audit-preview-actions">';
        echo '<a href="' . esc_url( $export_url ) . '" class="button button-primary">' . esc_html__( 'Download PDF', 'satori-audit' ) . '</a>';
        echo '</p>';
}

echo \Satori_Audit\Reports::get_report_html( (int) $report_id );
