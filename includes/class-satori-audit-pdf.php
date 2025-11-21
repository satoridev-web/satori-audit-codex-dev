<?php
/**
 * PDF export handler.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle PDF rendering and diagnostics.
 */
class Satori_Audit_Pdf {
    /**
     * Render a PDF for the given report.
     *
     * @param int    $report_id Report post ID.
     * @param string $html      HTML body to render.
     */
    public function render_pdf( int $report_id, string $html ): void {
        do_action( 'satori_audit_before_render_pdf', $report_id );

        $settings = Satori_Audit_Plugin::get_settings();
        $filename = apply_filters( 'satori_audit_pdf_filename', 'satori-audit-' . $report_id . '.pdf', $report_id );

        if ( class_exists( Dompdf::class ) ) {
            $options = new Options();
            $options->set( 'isRemoteEnabled', true );
            $dompdf = new Dompdf( $options );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( $settings['pdf_page_size'] ?: 'A4', $settings['pdf_orientation'] ?: 'portrait' );
            $dompdf->render();

            $dompdf->stream( $filename, [ 'Attachment' => true ] );
        } else {
            // Fallback to HTML print-friendly output.
            nocache_headers();
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'Content-Disposition: inline; filename=' . $filename . '.html' );
            echo '<style>body{font-family:Arial, sans-serif;}@media print {.no-print{display:none}}</style>';
            echo '<div class="no-print" style="padding:12px;background:#f6f7f7;border-bottom:1px solid #ccd0d4">';
            echo esc_html__( 'PDF engine not detected. Use your browser print dialog to save as PDF.', 'satori-audit' );
            echo '</div>';
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        do_action( 'satori_audit_after_render_pdf', $report_id );
    }

    /**
     * Return diagnostic info about the PDF engine.
     *
     * @return array
     */
    public function get_diagnostics(): array {
        $dompdf_available = class_exists( Dompdf::class );

        return [
            'engine'   => $dompdf_available ? 'DOMPDF' : __( 'Browser print styles', 'satori-audit' ),
            'status'   => $dompdf_available ? __( 'ready', 'satori-audit' ) : __( 'fallback', 'satori-audit' ),
            'messages' => $dompdf_available
                ? [ __( 'DOMPDF detected. Exports will render server-side.', 'satori-audit' ) ]
                : [ __( 'DOMPDF not installed. Browser print-to-PDF fallback active.', 'satori-audit' ) ],
        ];
    }
}
