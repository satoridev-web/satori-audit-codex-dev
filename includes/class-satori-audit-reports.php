<?php
/**
 * Report generation and retrieval services.
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
 * Provide report lifecycle helpers.
 */
class Reports {
	/**
	 * Wire runtime hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_satori_audit_generate_report', array( self::class, 'handle_generate_request' ) );
	}

	/**
	 * Handle the admin-post request to generate the current month report.
	 *
	 * @return void
	 */
	public static function handle_generate_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to generate reports.', 'satori-audit' ) );
		}

		check_admin_referer( 'satori_audit_generate_report' );

		$redirect  = admin_url( 'admin.php?page=satori-audit' );
		$report_id = self::generate_current_month();

		if ( $report_id > 0 ) {
			$redirect = add_query_arg( 'satori_audit_notice', 'report_generated', $redirect );
		} else {
			$redirect = add_query_arg( 'satori_audit_notice', 'report_failed', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Generate or refresh the current month report.
	 *
	 * @return int Report post ID or 0 on failure.
	 */
	public static function generate_current_month(): int {
		$period    = gmdate( 'Y-m' );
		$report_id = self::get_report_id_by_period( $period );

		if ( ! $report_id ) {
			$report_id = wp_insert_post(
				array(
					'post_type'   => 'satori_audit_report',
					'post_status' => 'publish',
					'post_title'  => sprintf( __( 'Audit Report – %s', 'satori-audit' ), $period ),
				)
			);

			if ( is_wp_error( $report_id ) ) {
				return 0;
			}
		}

		update_post_meta( (int) $report_id, '_satori_audit_period', $period );

		Plugins_Service::refresh_plugins_for_report( (int) $report_id );

		return (int) $report_id;
	}

	/**
	 * Get a report post ID for a given period.
	 *
	 * @param string $period Period key (YYYY-MM).
	 * @return int|null
	 */
	public static function get_report_id_by_period( string $period ): ?int {
		$query = new WP_Query(
			array(
				'post_type'      => 'satori_audit_report',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_key'       => '_satori_audit_period',
				'meta_value'     => $period,
			)
		);

		return $query->have_posts() ? (int) $query->posts[0] : null;
	}

	/**
	 * Fetch plugin rows for a report.
	 *
	 * @param int $report_id Report post ID.
	 * @return array
	 */
	public static function get_plugin_rows( int $report_id ): array {
		global $wpdb;

		$table = Tables::table( 'plugins' );

		if ( empty( $table ) ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE report_id = %d ORDER BY plugin_name ASC", $report_id ),
			ARRAY_A
		);
	}
}
