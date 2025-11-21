<?php
/**
 * PDF export placeholder.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle PDF rendering and diagnostics stubs.
 */
class Satori_Audit_Pdf {
    /**
     * Render a PDF for the given report.
     *
     * @param int $report_id Report post ID.
     */
    public function render_pdf( int $report_id ): void {
        do_action( 'satori_audit_before_render_pdf', $report_id );

        // Placeholder for PDF generation implementation.

        do_action( 'satori_audit_after_render_pdf', $report_id );
    }

    /**
     * Return diagnostic info about the PDF engine.
     *
     * @return array
     */
    public function get_diagnostics(): array {
        return [
            'engine'   => 'pending',
            'status'   => 'unconfigured',
            'messages' => [ __( 'PDF engine diagnostics will be displayed here.', 'satori-audit' ) ],
        ];
    }
}
