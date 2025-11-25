<?php
/**
 * PDF export handler.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle PDF rendering and diagnostics.
 */
class PDF {
    /**
     * Render a PDF for the given report.
     *
     * @param int $report_id Report post ID.
     * @return string Full path to generated PDF, or empty string on failure.
     */
    public static function generate_pdf( int $report_id ): string {
        $settings = self::get_settings();

        if ( 'none' === $settings['pdf_engine'] ) {
            self::log( 'PDF generation skipped: engine set to none.', $settings );
            return '';
        }

        $html = Reports::get_report_html( $report_id );
        $html = self::build_html( $html, $settings );

        $engine = self::load_engine( $settings );

        if ( empty( $engine ) ) {
            self::log( 'PDF generation aborted: no engine available.', $settings );
            return '';
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'satori-audit/';

        if ( ! wp_mkdir_p( $base_dir ) ) {
            self::log( 'Failed to create PDF output directory: ' . $base_dir, $settings );
            return '';
        }

        $filename = apply_filters( 'satori_audit_pdf_filename', 'satori-audit-' . $report_id . '.pdf', $report_id );
        $path     = $base_dir . $filename;

        if ( 'dompdf' === $engine['type'] ) {
            /** @var Dompdf $dompdf */
            $dompdf = $engine['instance'];
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( $settings['pdf_paper_size'], $settings['pdf_orientation'] );
            $dompdf->render();

            file_put_contents( $path, $dompdf->output() );
        } elseif ( 'tcpdf' === $engine['type'] ) {
            /** @var \TCPDF $tcpdf */
            $tcpdf = $engine['instance'];
            $tcpdf->AddPage();
            $tcpdf->writeHTML( $html, true, false, true, false, '' );
            $tcpdf->Output( $path, 'F' );
        }

        self::log( 'Generated PDF using ' . strtoupper( (string) $engine['type'] ) . ': ' . $path, $settings );

        return $path;
    }

    /**
     * Ensure report HTML is ready for PDF output.
     *
     * @param string $html     Raw HTML.
     * @param array  $settings Plugin settings.
     * @return string
     */
    private static function build_html( string $html, array $settings ): string {
        $prepared = self::ensure_document_wrapper( $html );
        $prepared = self::apply_header_footer( $prepared, $settings );

        return self::absolutize_urls( $prepared );
    }

    /**
     * Guarantee the HTML has a document wrapper for PDF engines.
     *
     * @param string $html Report markup.
     * @return string
     */
    private static function ensure_document_wrapper( string $html ): string {
        $has_html = str_contains( $html, '<html' );
        $has_body = str_contains( $html, '<body' );

        if ( $has_html && $has_body ) {
            return $html;
        }

        $content = $html;

        if ( $has_html ) {
            $content = preg_replace( '/^.*?<head[^>]*>.*?<\/head>/is', '', $content );
            $content = preg_replace( '/^.*?<html[^>]*>/is', '', $content );
            $content = preg_replace( '/<\/html>.*$/is', '', $content );
        }

        if ( $has_body ) {
            return '<!DOCTYPE html><html><head></head>' . $content . '</html>';
        }

        return '<!DOCTYPE html><html><head></head><body>' . $content . '</body></html>';
    }

    /**
     * Apply header/footer markup.
     *
     * @param string $html     Report HTML.
     * @param array  $settings Plugin settings.
     * @return string
     */
    private static function apply_header_footer( string $html, array $settings ): string {
        $logo   = '';
        $footer = '';

        if ( ! empty( $settings['pdf_logo_url'] ) ) {
            $logo_url = self::absolutize_url( $settings['pdf_logo_url'] );
            $logo     = '<div class="satori-pdf__header"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Report Logo', 'satori-audit' ) . '" /></div>';
        }

        if ( ! empty( $settings['pdf_footer_text'] ) ) {
            $footer = '<div class="satori-pdf__footer">' . wp_kses_post( $settings['pdf_footer_text'] ) . '</div>';
        }

        if ( empty( $logo ) && empty( $footer ) ) {
            return $html;
        }

        $style = '<style>'
            . '.satori-pdf__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb;}'
            . '.satori-pdf__header img{max-height:48px;width:auto;}'
            . '.satori-pdf__footer{position:fixed;bottom:0;left:0;right:0;padding:10px 16px;border-top:1px solid #e5e7eb;font-size:12px;color:#4b5563;text-align:center;background:#fff;}'
            . 'body{margin-bottom:72px;}'
            . '</style>';

        $injected = $html;

        if ( str_contains( $injected, '</head>' ) ) {
            $injected = str_replace( '</head>', $style . '</head>', $injected );
        } else {
            $injected = $style . $injected;
        }

        if ( $logo ) {
            $injected = str_replace( '<body>', '<body>' . $logo, $injected );
        }

        if ( $footer ) {
            $injected = str_replace( '</body>', $footer . '</body>', $injected );
        }

        return $injected;
    }

    /**
     * Convert relative URLs to absolute ones.
     *
     * @param string $html HTML to convert.
     * @return string
     */
    private static function absolutize_urls( string $html ): string {
        return (string) preg_replace_callback(
            '/\b(href|src)="(?!https?:|data:|mailto:|#)([^\"]+)"/i',
            static function ( array $matches ): string {
                $absolute = self::absolutize_url( $matches[2] );

                return $matches[1] . '="' . esc_url_raw( $absolute ) . '"';
            },
            $html
        );
    }

    /**
     * Create an absolute URL from a relative path.
     *
     * @param string $url URL to normalise.
     * @return string
     */
    private static function absolutize_url( string $url ): string {
        if ( empty( $url ) ) {
            return $url;
        }

        if ( preg_match( '/^https?:\/\//i', $url ) ) {
            return $url;
        }

        return trailingslashit( home_url() ) . ltrim( $url, '/' );
    }

    /**
     * Load the configured PDF engine if available.
     *
     * @param array $settings Plugin settings.
     * @return array{type:string,instance:object}|array
     */
    private static function load_engine( array $settings ): array {
        $preference = $settings['pdf_engine'];
        self::log( 'Attempting to load PDF engine: ' . $preference, $settings );

        $candidates = array();

        if ( 'dompdf' === $preference ) {
            $candidates = array( 'dompdf', 'tcpdf' );
        } elseif ( 'tcpdf' === $preference ) {
            $candidates = array( 'tcpdf', 'dompdf' );
        } else {
            $candidates = array( $preference );
        }

        foreach ( $candidates as $engine ) {
            if ( 'dompdf' === $engine && class_exists( Dompdf::class ) ) {
                $options = new Options();
                $options->set( 'isRemoteEnabled', true );
                $options->setDefaultFont( $settings['pdf_font_family'] );

                if ( $engine !== $preference ) {
                    self::log( 'Falling back to DOMPDF engine.', $settings );
                }

                return array(
                    'type'     => 'dompdf',
                    'instance' => new Dompdf( $options ),
                );
            }

            if ( 'tcpdf' === $engine && class_exists( '\\TCPDF' ) ) {
                $orientation = 'landscape' === $settings['pdf_orientation'] ? 'L' : 'P';
                $tcpdf       = new \TCPDF( $orientation, 'mm', $settings['pdf_paper_size'], true, 'UTF-8', false );
                $tcpdf->SetCreator( 'Satori Audit' );
                $tcpdf->SetAuthor( get_bloginfo( 'name' ) );
                $tcpdf->setPrintHeader( false );
                $tcpdf->setPrintFooter( false );
                $tcpdf->SetMargins( 15, 18, 15 );
                $tcpdf->SetFont( $settings['pdf_font_family'], '', 10 );

                if ( $engine !== $preference ) {
                    self::log( 'Falling back to TCPDF engine.', $settings );
                }

                return array(
                    'type'     => 'tcpdf',
                    'instance' => $tcpdf,
                );
            }

            self::log( strtoupper( (string) $engine ) . ' requested but not available.', $settings );
        }

        self::log( 'No suitable PDF engine available.', $settings );

        return array();
    }

    /**
     * Log a message when debug mode is enabled.
     *
     * @param string $message  Message to log.
     * @param array  $settings Plugin settings.
     * @return void
     */
    private static function log( string $message, array $settings ): void {
        if ( empty( $settings['debug_mode'] ) ) {
            return;
        }

        if ( function_exists( 'satori_audit_log' ) ) {
            satori_audit_log( $message );
        }
    }

    /**
     * Fetch plugin settings with sane defaults.
     *
     * @return array
     */
    private static function get_settings(): array {
        $defaults = array(
            'pdf_engine'          => 'none',
            'pdf_paper_size'      => 'A4',
            'pdf_orientation'     => 'portrait',
            'pdf_font_family'     => 'Helvetica',
            'pdf_logo_url'        => '',
            'pdf_footer_text'     => '',
            'display_date_format' => 'Y-m-d',
            'debug_mode'          => 0,
        );

        $settings = Plugin::get_settings();

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return array_merge( $defaults, $settings );
    }
}
