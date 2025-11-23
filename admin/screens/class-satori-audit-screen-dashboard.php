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
	
		$notice = isset( $_GET['satori_audit_notice'] ) ? sanitize_key( wp_unslash( $_GET['satori_audit_notice'] ) ) : '';
	
		if ( 'report_generated' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Report generated for the current month.', 'satori-audit' ) . '</p></div>';
		} elseif ( 'report_failed' === $notice ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to generate the report. Please try again.', 'satori-audit' ) . '</p></div>';
		}
	
		echo '<p>' . esc_html__( 'Generate and refresh your monthly audit report.', 'satori-audit' ) . '</p>';
		echo '<div class="satori-audit-panel">';
		echo '<h2>' . esc_html__( 'Generate Current Month Report', 'satori-audit' ) . '</h2>';
		echo '<p>' . esc_html__( 'Creates or updates the audit report for the current month and captures the latest plugin inventory.', 'satori-audit' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'satori_audit_generate_report' );
		echo '<input type="hidden" name="action" value="satori_audit_generate_report" />';
		submit_button( __( 'Generate Current Month Report', 'satori-audit' ), 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}
}
