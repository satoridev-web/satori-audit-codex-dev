<?php
/**
 * Archive admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

use WP_Query;

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
		$selected_report_id = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0;
		$report            = $selected_report_id ? get_post( $selected_report_id ) : null;
		$period            = $selected_report_id ? get_post_meta( $selected_report_id, '_satori_audit_period', true ) : '';
		$plugin_rows       = array();

		if ( $selected_report_id && $report instanceof \WP_Post ) {
			$plugin_rows = Reports::get_plugin_rows( $selected_report_id );
		}

		echo '<div class="wrap satori-audit-wrap">';
		echo '<h1>' . esc_html__( 'SATORI Audit – Archive', 'satori-audit' ) . '</h1>';

		if ( $selected_report_id && $report instanceof \WP_Post ) {
			$report_title = sprintf( esc_html__( 'Report Preview: %s', 'satori-audit' ), esc_html( $period ?: $report->post_title ) );
			echo '<h2>' . $report_title . '</h2>';
			include SATORI_AUDIT_PATH . 'templates/admin/report-preview.php';
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'satori_audit_report',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		echo '<h2>' . esc_html__( 'Report Archive', 'satori-audit' ) . '</h2>';

		if ( ! $query->have_posts() ) {
			echo '<p>' . esc_html__( 'No reports found. Use the Dashboard to generate your first report.', 'satori-audit' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Period', 'satori-audit' ) . '</th><th>' . esc_html__( 'Generated On', 'satori-audit' ) . '</th><th>' . esc_html__( 'Actions', 'satori-audit' ) . '</th></tr></thead>';
		echo '<tbody>';

		foreach ( $query->posts as $archive_post ) {
			$archive_period = get_post_meta( $archive_post->ID, '_satori_audit_period', true );
			$view_url       = add_query_arg(
				array(
					'page'      => 'satori-audit-archive',
					'report_id' => $archive_post->ID,
				),
				admin_url( 'admin.php' )
			);
			$generated_on   = mysql2date( get_option( 'date_format' ), $archive_post->post_date );

			echo '<tr>';
			echo '<td>' . esc_html( $archive_period ?: __( 'Unknown', 'satori-audit' ) ) . '</td>';
			echo '<td>' . esc_html( $generated_on ) . '</td>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'satori-audit' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}
}
