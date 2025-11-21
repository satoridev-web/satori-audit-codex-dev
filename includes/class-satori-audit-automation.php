<?php
/**
 * Automation entry points.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle scheduled tasks and hooks.
 */
class Satori_Audit_Automation {
    /**
     * Cron hook name.
     */
    public const CRON_HOOK = 'satori_audit_generate_monthly_report';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'run_scheduled_generation' ] );
        add_action( 'admin_init', [ $this, 'maybe_schedule' ] );
    }

    /**
     * Schedule a recurring cron event if enabled.
     */
    public function maybe_schedule(): void {
        $settings = Satori_Audit_Plugin::get_settings();

        if ( empty( $settings['enable_cron'] ) ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            return;
        }

        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        wp_schedule_event( time(), 'daily', self::CRON_HOOK );
    }

    /**
     * Scheduled report generation and email dispatch.
     */
    public function run_scheduled_generation(): void {
        $settings = Satori_Audit_Plugin::get_settings();

        if ( empty( $settings['enable_cron'] ) ) {
            return;
        }

        $today = (int) gmdate( 'j' );
        $hour  = (int) gmdate( 'H' );
        $cron_hour = (int) substr( (string) $settings['cron_time'], 0, 2 );

        if ( $today !== (int) $settings['cron_day'] || $hour !== $cron_hour ) {
            return;
        }

        $reports   = new Satori_Audit_Reports();
        $report_id = $reports->generate_report( gmdate( 'Y-m' ) );

        if ( ! $report_id ) {
            return;
        }

        $reports->lock_report( $report_id );

        $this->email_pdf_notice( $report_id, $settings );
    }

    /**
     * Render HTML for email/PDF use.
     */
    private function get_report_html( int $report_id ): string {
        ob_start();
        $report_service = new Satori_Audit_Reports();
        $settings       = Satori_Audit_Plugin::get_settings();
        $plugin_rows    = $report_service->get_plugin_rows( $report_id );
        $security_rows  = $report_service->get_security_rows( $report_id );
        $meta           = $report_service->get_report_meta( $report_id );
        $summary        = $report_service->get_summary( $report_id );
        $period         = get_post_meta( $report_id, '_satori_audit_period', true );

        include SATORI_AUDIT_PLUGIN_DIR . 'templates/admin/report-preview.php';

        return (string) ob_get_clean();
    }

    /**
     * Dispatch email notice with PDF instructions.
     */
    private function email_pdf_notice( int $report_id, array $settings ): void {
        if ( empty( $settings['default_recipients'] ) ) {
            return;
        }

        $subject = sprintf( __( 'SATORI Audit report %s', 'satori-audit' ), get_post_meta( $report_id, '_satori_audit_period', true ) );
        $body    = __( 'The monthly audit report has been generated. Visit the dashboard to download the PDF.', 'satori-audit' );
        wp_mail( $settings['default_recipients'], $subject, $body, [ 'From: ' . $settings['from_email'] ] );
    }
}
