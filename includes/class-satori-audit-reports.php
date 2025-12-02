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
                add_action( 'admin_post_satori_audit_export_pdf', array( self::class, 'handle_export_request' ) );
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

                if ( function_exists( 'satori_audit_log' ) ) {
                        satori_audit_log( 'Report generation request received via admin action.' );
                }

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
         * Handle the admin-post request to export a report as PDF.
         *
         * @return void
         */
        public static function handle_export_request(): void {
                $settings      = Screen_Settings::get_settings();
                $capabilities  = Screen_Settings::get_capabilities();
                $view_cap      = $capabilities['view'];
                $nonce_name    = '_satori_audit_nonce';
                $redirect_fall = admin_url( 'admin.php?page=satori-audit-archive' );

                if ( ! current_user_can( $view_cap ) ) {
                        Screen_Settings::log_debug( 'Export denied for user ID ' . get_current_user_id() . '.', $settings );
                        wp_die( esc_html__( 'You do not have permission to export SATORI Audit reports.', 'satori-audit' ) );
                }

                $nonce_value = isset( $_REQUEST[ $nonce_name ] ) ? wp_unslash( $_REQUEST[ $nonce_name ] ) : '';

                if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'satori_audit_export_pdf' ) ) {
                        Screen_Settings::log_debug( 'Export request failed nonce verification.', $settings );
                        wp_die( esc_html__( 'Invalid request. Please try again.', 'satori-audit' ) );
                }

                $report_id = isset( $_REQUEST['report_id'] ) ? absint( wp_unslash( $_REQUEST['report_id'] ) ) : 0;
                $report    = $report_id ? get_post( $report_id ) : null;

                if ( ! $report instanceof \WP_Post || 'satori_audit_report' !== $report->post_type ) {
                        Screen_Settings::log_debug( 'Export request received for invalid report ID ' . $report_id . '.', $settings );
                        self::redirect_with_pdf_error( $redirect_fall );
                }

                Screen_Settings::log_debug(
                        sprintf(
                                'Export request for report ID %d by user ID %d.',
                                $report_id,
                                get_current_user_id()
                        ),
                        $settings
                );

                ob_start();

                $pdf_path = PDF::generate_pdf( (int) $report_id );
                $buffer   = ob_get_clean();

                if ( '' !== trim( (string) $buffer ) && function_exists( 'satori_audit_log' ) ) {
                        $snippet = function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $buffer ), 0, 300 ) : substr( trim( (string) $buffer ), 0, 300 );

                        satori_audit_log( '[PDF] Unexpected output during PDF generation: ' . $snippet );
                }

                if ( empty( $pdf_path ) || ! file_exists( $pdf_path ) ) {
                        Screen_Settings::log_debug( 'PDF generation failed for report ID ' . $report_id . '.', $settings );
                        self::redirect_with_pdf_error( $redirect_fall );
                }

                if ( ! is_readable( $pdf_path ) ) {
                        Screen_Settings::log_debug( 'PDF file is not readable for report ID ' . $report_id . '.', $settings );
                        self::redirect_with_pdf_error( $redirect_fall );
                }

                while ( ob_get_level() > 0 ) {
                        ob_end_clean();
                }

                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: inline; filename="' . basename( $pdf_path ) . '"' );
                header( 'Content-Length: ' . (string) filesize( $pdf_path ) );

                readfile( $pdf_path );
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

                if ( function_exists( 'satori_audit_log' ) ) {
                        satori_audit_log( 'Generating report for period ' . $period . '.' );
                }

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

                $updates = Plugins_Service::get_plugin_update_history( (int) $report_id );

                if ( ! empty( $updates ) ) {
                        update_post_meta( (int) $report_id, '_satori_audit_plugin_updates', $updates );
                }

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

        /**
         * Return a rendered HTML document for the provided report.
         *
         * @param int $report_id Report post ID.
         * @return string
         */
        public static function get_report_html( int $report_id ): string {
                $settings = Settings::get_settings();

                self::log_debug( 'Begin rendering report HTML for ID ' . $report_id, $settings );

                $metadata        = self::get_report_metadata( $report_id );
                $header          = self::render_header( $settings );
                $summary_section = self::render_summary_section( $report_id, $settings, $metadata );
                $updates_section = self::render_plugin_updates( $report_id, $settings );
                $diagnostics     = self::render_diagnostics( $settings );

                $body = $header . $summary_section . $updates_section . $diagnostics;

                self::log_debug( 'Completed rendering report HTML for ID ' . $report_id, $settings );

                return self::wrap_html( $body );
        }

        /**
         * Output the rendered report HTML (used for admin preview).
         *
         * @param int $report_id Report post ID.
         * @return void
         */
        public static function render( int $report_id ): void {
                echo self::get_report_html( $report_id );
        }

        /**
         * Redirect back to admin with a PDF error flag.
         *
         * @param string $fallback_url Fallback URL if no referer is present.
         * @return void
         */
        private static function redirect_with_pdf_error( string $fallback_url ): void {
                $redirect = wp_get_referer();
                $redirect = $redirect ? $redirect : $fallback_url;

                $redirect = add_query_arg( 'pdf_error', '1', $redirect );

                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * Fetch plugin + display settings with safe defaults.
         *
         * @return array
         */
        private static function get_settings(): array {
                return Settings::get_settings();
        }

        /**
         * Collect metadata for a report post.
         *
         * @param int $report_id Report post ID.
         * @return array
         */
        private static function get_report_metadata( int $report_id ): array {
                $report = get_post( $report_id );

                if ( ! $report instanceof \WP_Post ) {
                        return array(
                                'title' => __( 'Audit Report', 'satori-audit' ),
                                'date'  => '',
                        );
                }

                return array(
                        'title' => get_the_title( $report ),
                        'date'  => $report->post_date,
                );
        }

        /**
         * Render the header section.
         *
         * @param array $settings Plugin settings.
         * @return string
         */
        private static function render_header( array $settings ): string {
                $date_format = self::get_date_format( $settings );
                $site_name   = ! empty( $settings['service_site_name'] ) ? (string) $settings['service_site_name'] : get_bloginfo( 'name' );
                $site_url    = ! empty( $settings['service_site_url'] ) ? (string) $settings['service_site_url'] : home_url();
                $managed_by  = $settings['service_managed_by'] ?? '';
                $client      = $settings['service_client'] ?? '';
                $start_date  = ! empty( $settings['service_start_date'] ) ? self::format_date( (string) $settings['service_start_date'], $date_format ) : '';

                $rows = array(
                        array(
                                'label' => __( 'Site Name', 'satori-audit' ),
                                'value' => esc_html( $site_name ),
                        ),
                        array(
                                'label' => __( 'Site URL', 'satori-audit' ),
                                'value' => sprintf( '<a href="%s">%s</a>', esc_url( $site_url ), esc_html( $site_url ) ),
                        ),
                        array(
                                'label' => __( 'Client', 'satori-audit' ),
                                'value' => esc_html( (string) $client ),
                        ),
                        array(
                                'label' => __( 'Managed By', 'satori-audit' ),
                                'value' => esc_html( (string) $managed_by ),
                        ),
                        array(
                                'label' => __( 'Service Start Date', 'satori-audit' ),
                                'value' => esc_html( $start_date ),
                        ),
                );

                self::log_debug( 'Rendered report header section.', $settings );

                ob_start();
                ?>
                <header class="satori-report__header">
                        <div class="satori-report__title-block">
                                <h1 class="satori-report__title"><?php echo esc_html__( 'SATORI Audit Report', 'satori-audit' ); ?></h1>
                                <p class="satori-report__subtitle"><?php echo esc_html__( 'Comprehensive overview of your site health and updates.', 'satori-audit' ); ?></p>
                        </div>
                        <dl class="satori-report__meta">
                                <?php foreach ( $rows as $row ) : ?>
                                        <div class="satori-report__meta-row">
                                                <dt><?php echo esc_html( $row['label'] ); ?></dt>
                                                <dd><?php echo wp_kses_post( $row['value'] ); ?></dd>
                                        </div>
                                <?php endforeach; ?>
                        </dl>
                </header>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Render the summary section.
         *
         * @param int   $report_id Report post ID.
         * @param array $settings  Plugin settings.
         * @param array $metadata  Report metadata.
         * @return string
         */
        private static function render_summary_section( int $report_id, array $settings, array $metadata ): string {
                $date_format   = self::get_date_format( $settings );
                $report_date   = ! empty( $metadata['date'] ) ? self::format_date( (string) $metadata['date'], $date_format ) : '';
                $title         = $metadata['title'] ?? sprintf( __( 'Report #%d', 'satori-audit' ), $report_id );
                $service_notes = $settings['service_notes'] ?? '';

                self::log_debug( 'Rendered summary section.', $settings );

                ob_start();
                ?>
                <section id="summary" class="satori-report__section">
                        <h2><?php echo esc_html__( 'Summary', 'satori-audit' ); ?></h2>
                        <div class="satori-report__summary">
                                <p class="satori-report__summary-title"><?php echo esc_html( $title ); ?></p>
                                <?php if ( ! empty( $report_date ) ) : ?>
                                        <p class="satori-report__summary-date"><?php echo esc_html__( 'Report Date', 'satori-audit' ); ?>: <?php echo esc_html( $report_date ); ?></p>
                                <?php endif; ?>
                                <p class="satori-report__summary-text"><?php echo esc_html__( 'This report highlights recent plugin updates, key site details, and diagnostic information collected for your records.', 'satori-audit' ); ?></p>
                                <?php if ( ! empty( $service_notes ) ) : ?>
                                        <div class="satori-report__service-notes">
                                                <h3><?php echo esc_html__( 'Service Notes', 'satori-audit' ); ?></h3>
                                                <div class="satori-report__service-notes-body"><?php echo wp_kses_post( $service_notes ); ?></div>
                                        </div>
                                <?php endif; ?>
                        </div>
                </section>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Render plugin update list.
         *
         * @param int   $report_id Report post ID.
         * @param array $settings  Plugin settings.
         * @return string
         */
        private static function render_plugin_updates( int $report_id, array $settings ): string {
                $date_format = self::get_date_format( $settings );
                $updates     = Plugins_Service::get_plugin_update_history( $report_id );

                self::log_debug( 'Rendered plugin update section.', $settings );

                ob_start();
                ?>
                <section id="plugin-updates" class="satori-report__section">
                        <h2><?php echo esc_html__( 'Plugin Updates', 'satori-audit' ); ?></h2>
                        <?php if ( empty( $updates ) ) : ?>
                                <p><?php echo esc_html__( 'No plugin updates recorded for this period.', 'satori-audit' ); ?></p>
                        <?php else : ?>
                                <div class="satori-report__plugin-list">
                                        <?php foreach ( $updates as $update ) :
                                                $name        = $update['plugin'] ?? '';
                                                $old_version = $update['old_version'] ?? '';
                                                $new_version = $update['new_version'] ?? '';
                                                $date_value  = $update['date'] ?? '';
                                                $date        = ! empty( $date_value ) ? self::format_date( (string) $date_value, $date_format ) : '';
                                                ?>
                                                <div class="satori-report__plugin-update">
                                                        <div class="satori-report__plugin-name"><?php echo esc_html( $name ); ?></div>
                                                        <dl class="satori-report__plugin-meta">
                                                                <div><dt><?php echo esc_html__( 'Previous Version', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $old_version ); ?></dd></div>
                                                                <div><dt><?php echo esc_html__( 'Updated Version', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $new_version ); ?></dd></div>
                                                                <?php if ( ! empty( $date ) ) : ?>
                                                                        <div><dt><?php echo esc_html__( 'Updated On', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $date ); ?></dd></div>
                                                                <?php endif; ?>
                                                        </dl>
                                                </div>
                                        <?php endforeach; ?>
                                </div>
                        <?php endif; ?>
                </section>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Render diagnostics block if enabled.
         *
         * @param array $settings Plugin settings.
         * @return string
         */
        private static function render_diagnostics( array $settings ): string {
                if ( empty( $settings['display_show_debug_section'] ) ) {
                        return '';
                }

                if ( ! function_exists( 'get_plugins' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $data = array(
                        'debug_mode'   => ! empty( $settings['debug_mode'] ) ? __( 'Enabled', 'satori-audit' ) : __( 'Disabled', 'satori-audit' ),
                        'timestamp'    => self::format_date( current_time( 'mysql' ), self::get_date_format( $settings ) ),
                        'wp_version'   => get_bloginfo( 'version' ),
                        'php_version'  => PHP_VERSION,
                        'active_theme' => wp_get_theme()->get( 'Name' ),
                        'plugin_count' => function_exists( 'get_plugins' ) ? count( get_plugins() ) : 0,
                );

                self::log_debug( 'Rendered diagnostics section.', $settings );

                ob_start();
                ?>
                <section id="diagnostics" class="satori-report__section">
                        <h2><?php echo esc_html__( 'Diagnostics', 'satori-audit' ); ?></h2>
                        <dl class="satori-report__diagnostics">
                                <div><dt><?php echo esc_html__( 'Debug Mode', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $data['debug_mode'] ); ?></dd></div>
                                <div><dt><?php echo esc_html__( 'Rendered On', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $data['timestamp'] ); ?></dd></div>
                                <div><dt><?php echo esc_html__( 'WordPress Version', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $data['wp_version'] ); ?></dd></div>
                                <div><dt><?php echo esc_html__( 'PHP Version', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $data['php_version'] ); ?></dd></div>
                                <div><dt><?php echo esc_html__( 'Active Theme', 'satori-audit' ); ?></dt><dd><?php echo esc_html( $data['active_theme'] ); ?></dd></div>
                                <div><dt><?php echo esc_html__( 'Total Plugins', 'satori-audit' ); ?></dt><dd><?php echo esc_html( (string) $data['plugin_count'] ); ?></dd></div>
                        </dl>
                </section>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Wrap the provided body content in a scoped preview container.
         *
         * @param string $body Report body HTML.
         * @return string
         */
        private static function wrap_html( string $body ): string {
                $styles = '
                        .satori-audit-report-preview { font-family: "Helvetica Neue", Arial, sans-serif; color: #1f2933; padding: 32px; line-height: 1.6; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.03); }
                        .satori-audit-report-preview h1, .satori-audit-report-preview h2, .satori-audit-report-preview h3, .satori-audit-report-preview h4 { color: #0b3b5c; margin-top: 0; }
                        .satori-audit-report-preview a { color: #0b7fc2; text-decoration: none; }
                        .satori-audit-report-preview a:hover { text-decoration: underline; }
                        .satori-audit-report-preview .satori-report__header { border-bottom: 2px solid #e5e7eb; padding-bottom: 24px; margin-bottom: 24px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; }
                        .satori-audit-report-preview .satori-report__title { margin: 0; font-size: 28px; }
                        .satori-audit-report-preview .satori-report__subtitle { margin: 4px 0 0; color: #52606d; }
                        .satori-audit-report-preview .satori-report__meta { margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 8px 16px; }
                        .satori-audit-report-preview .satori-report__meta-row { display: flex; justify-content: space-between; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; background: #f9fafb; }
                        .satori-audit-report-preview .satori-report__meta-row dt { font-weight: 600; color: #52606d; }
                        .satori-audit-report-preview .satori-report__meta-row dd { margin: 0; text-align: right; }
                        .satori-audit-report-preview .satori-report__section { margin-bottom: 32px; }
                        .satori-audit-report-preview .satori-report__summary { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
                        .satori-audit-report-preview .satori-report__summary-title { font-size: 20px; margin: 0 0 4px; }
                        .satori-audit-report-preview .satori-report__summary-date { margin: 0 0 8px; color: #52606d; }
                        .satori-audit-report-preview .satori-report__summary-text { margin: 0; }
                        .satori-audit-report-preview .satori-report__service-notes { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; }
                        .satori-audit-report-preview .satori-report__service-notes h3 { margin: 0 0 6px; font-size: 16px; }
                        .satori-audit-report-preview .satori-report__service-notes-body { margin: 0; color: #1f2933; }
                        .satori-audit-report-preview .satori-report__plugin-list { display: grid; gap: 12px; }
                        .satori-audit-report-preview .satori-report__plugin-update { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; }
                        .satori-audit-report-preview .satori-report__plugin-name { font-weight: 700; font-size: 16px; margin-bottom: 8px; }
                        .satori-audit-report-preview .satori-report__plugin-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 4px 12px; margin: 0; padding: 0; }
                        .satori-audit-report-preview .satori-report__plugin-meta dt { font-weight: 600; color: #52606d; }
                        .satori-audit-report-preview .satori-report__plugin-meta dd { margin: 0; }
                        .satori-audit-report-preview .satori-report__diagnostics { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 6px 12px; margin: 0; padding: 0; }
                        .satori-audit-report-preview .satori-report__diagnostics dt { font-weight: 600; color: #52606d; }
                        .satori-audit-report-preview .satori-report__diagnostics dd { margin: 0; }
                ';

                ob_start();
                ?>
                <div class="satori-audit-report satori-audit-report-preview">
                        <style><?php echo $styles; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
                        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
                <?php
                return (string) ob_get_clean();
        }

        /**
         * Format a date string using the configured display format.
         *
         * @param string $value Raw date value.
         * @param string $format Desired format.
         * @return string
         */
        private static function format_date( string $value, string $format ): string {
                $timestamp = strtotime( $value );

                if ( false === $timestamp ) {
                        return $value;
                }

                return gmdate( $format, $timestamp );
        }

        /**
         * Retrieve configured date format with fallback.
         *
         * @param array $settings Settings array.
         * @return string
         */
        private static function get_date_format( array $settings ): string {
                $format = $settings['display_date_format'] ?? '';

                if ( empty( $format ) || ! is_string( $format ) ) {
                        $format = 'j F Y';
                }

                return $format;
        }

        /**
         * Write a log line when debug mode is enabled.
         *
         * @param string $message  Log message.
         * @param array  $settings Settings array.
         * @return void
         */
        private static function log_debug( string $message, array $settings ): void {
                if ( empty( $settings['debug_mode'] ) ) {
                        return;
                }

                if ( function_exists( 'satori_audit_log' ) ) {
                        satori_audit_log( $message );
                }
        }
}
