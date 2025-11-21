<?php
/**
 * Archive admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

use Satori_Audit\Includes\Satori_Audit_Plugin;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Archive page for SATORI Audit.
 */
class Satori_Audit_Screen_Archive {
    /**
     * Display archive content.
     */
    public function render(): void {
        $period_filter = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_args    = [
            'post_type'      => 'satori_audit_report',
            'posts_per_page' => 20,
            'post_status'    => 'any',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_key'       => '_satori_audit_period',
        ];

        if ( $period_filter ) {
            $query_args['meta_query'] = [
                [
                    'key'   => '_satori_audit_period',
                    'value' => $period_filter,
                ],
            ];
        }

        $query = new WP_Query( $query_args );

        echo '<div class="wrap satori-audit-wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Archive', 'satori-audit' ) . '</h1>';
        echo '<form method="get" class="satori-audit-toolbar">';
        echo '<input type="hidden" name="page" value="satori-audit-archive" />';
        echo '<label>' . esc_html__( 'Filter by period (YYYY-MM)', 'satori-audit' ) . '</label> ';
        echo '<input type="text" name="period" value="' . esc_attr( $period_filter ) . '" /> ';
        submit_button( __( 'Filter', 'satori-audit' ), 'secondary', '', false );
        echo '</form>';

        echo '<table class="widefat striped satori-audit-table">';
        echo '<thead><tr>';
        $headers = [ 'Period', 'Title', 'Status', 'Summary', 'Actions' ];
        foreach ( $headers as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $period  = get_post_meta( get_the_ID(), '_satori_audit_period', true );
                $summary = get_post_meta( get_the_ID(), '_satori_audit_summary', true );
                $locked  = get_post_meta( get_the_ID(), '_satori_audit_locked', true );
                $summary_text = is_array( $summary ) ? sprintf( __( '%1$d new, %2$d updated, %3$d deleted', 'satori-audit' ), $summary['new'] ?? 0, $summary['updated'] ?? 0, $summary['deleted'] ?? 0 ) : '';

                echo '<tr>';
                echo '<td>' . esc_html( $period ) . '</td>';
                echo '<td>' . esc_html( get_the_title() ) . '</td>';
                echo '<td>' . ( $locked ? esc_html__( 'Locked', 'satori-audit' ) : esc_html__( 'Open', 'satori-audit' ) ) . '</td>';
                echo '<td>' . esc_html( $summary_text ) . '</td>';
                echo '<td><a class="button" href="' . esc_url( admin_url( 'admin.php?page=satori-audit&period=' . urlencode( $period ) ) ) . '">' . esc_html__( 'Open', 'satori-audit' ) . '</a></td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="5" class="empty">' . esc_html__( 'No reports found.', 'satori-audit' ) . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}
