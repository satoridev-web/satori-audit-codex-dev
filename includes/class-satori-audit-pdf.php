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

        try {
            $html = Reports::get_report_html( $report_id );
            $html = self::build_html( $html, $settings );

            if ( empty( trim( $html ) ) ) {
                self::log( 'PDF generation failed for report ' . $report_id . ': empty HTML.', $settings );

                return '';
            }

            $engine = self::load_engine( $settings );

            if ( empty( $engine ) ) {
                self::log( 'PDF generation aborted for report ' . $report_id . ': no engine available.', $settings );
                return '';
            }

            $paper       = self::normalise_paper_size( $settings['pdf_paper_size'] );
            $orientation = self::normalise_orientation( $settings['pdf_orientation'] );
            $font_family = self::normalise_font_family( $settings['pdf_font_family'] );
            $path        = self::get_pdf_output_path( $report_id );

            if ( empty( $path ) ) {
                self::log( 'Failed to determine PDF output path for report ' . $report_id . '.', $settings );
                return '';
            }

            $output = '';

            if ( 'dompdf' === $engine['type'] ) {
                /** @var Dompdf $dompdf */
                $dompdf = $engine['instance'];
                $dompdf->setPaper( $paper, $orientation );
                $dompdf->loadHtml( $html );
                $dompdf->render();

                $output = $dompdf->output();
            } elseif ( 'tcpdf' === $engine['type'] ) {
                /** @var \TCPDF $tcpdf */
                $tcpdf        = $engine['instance'];
                $tcpdf_orient = 'landscape' === $orientation ? 'L' : 'P';

                $tcpdf->SetFont( $font_family, '', 10 );
                $tcpdf->AddPage( $tcpdf_orient, $paper );
                $tcpdf->writeHTML( $html, true, false, true, false, '' );

                $output = $tcpdf->Output( '', 'S' );
            }

            if ( empty( $output ) ) {
                self::log( 'PDF generation failed for report ' . $report_id . ': empty engine output.', $settings );

                return '';
            }

            if ( false === file_put_contents( $path, $output ) ) {
                self::log( 'Failed to write PDF for report ' . $report_id . ' to ' . $path, $settings );

                return '';
            }

            self::log( 'Generated PDF for report ' . $report_id . ' using ' . strtoupper( (string) $engine['type'] ) . ': ' . $path, $settings );

            return $path;
        } catch ( \Throwable $e ) {
            self::log(
                sprintf(
                    'PDF generation error for report %d (engine: %s): %s',
                    $report_id,
                    $settings['pdf_engine'],
                    $e->getMessage()
                ),
                $settings
            );

            return '';
        }
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
        $prepared = self::add_body_class( $prepared, 'satori-audit-pdf' );
        $prepared = self::inject_pdf_styles( $prepared );
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

        if ( ! $has_html || ! $has_body ) {
            $content = $html;

            if ( $has_html ) {
                $content = preg_replace( '/^.*?<head[^>]*>.*?<\/head>/is', '', $content );
                $content = preg_replace( '/^.*?<html[^>]*>/is', '', $content );
                $content = preg_replace( '/<\/html>.*$/is', '', $content );
            }

            $html = $has_body
                ? '<!DOCTYPE html><html><head></head>' . $content . '</html>'
                : '<!DOCTYPE html><html><head></head><body>' . $content . '</body></html>';
        }

        if ( ! str_contains( $html, '<head' ) ) {
            $html = preg_replace( '/<html([^>]*)>/i', '<html$1><head></head>', $html, 1 );
        }

        if ( ! preg_match( '/<meta[^>]+charset=/i', $html ) ) {
            $html = preg_replace( '/<head(.*?)>/i', '<head$1><meta charset="utf-8" />', $html, 1 );
        }

        return $html;
    }

    /**
     * Add a class attribute to the body tag if it is missing.
     *
     * @param string $html  Report HTML.
     * @param string $class Class name to add.
     * @return string
     */
    private static function add_body_class( string $html, string $class ): string {
        if ( ! str_contains( $html, '<body' ) ) {
            return $html;
        }

        if ( preg_match( '/<body[^>]+class="([^"]*)"/i', $html, $matches ) ) {
            $classes = explode( ' ', $matches[1] );

            if ( in_array( $class, $classes, true ) ) {
                return $html;
            }

            $classes[] = $class;
            $replacement = '<body class="' . implode( ' ', array_filter( $classes ) ) . '"';

            return (string) preg_replace( '/<body[^>]+class="[^"]*"/i', $replacement, $html, 1 );
        }

        return (string) preg_replace( '/<body(.*?)>/i', '<body$1 class="' . $class . '">', $html, 1 );
    }

    /**
     * Collect and inject PDF styles into the document head.
     *
     * @param string $html Report HTML.
     * @return string
     */
    private static function inject_pdf_styles( string $html ): string {
        $inline_styles = array();

        $clean_html = (string) preg_replace_callback(
            '/<style[^>]*>(.*?)<\/style>/is',
            static function ( array $matches ) use ( &$inline_styles ): string {
                $inline_styles[] = trim( $matches[1] );

                return '';
            },
            $html
        );

        $styles = array_filter(
            array(
                self::get_pdf_styles(),
                implode( "\n", $inline_styles ),
            )
        );

        $style_block = '<style>' . implode( "\n\n", $styles ) . '</style>';

        if ( str_contains( $clean_html, '</head>' ) ) {
            return (string) preg_replace( '/<\/head>/i', $style_block . '</head>', $clean_html, 1 );
        }

        if ( preg_match( '/<html[^>]*>/i', $clean_html ) ) {
            return (string) preg_replace( '/<html([^>]*)>/i', '<html$1><head>' . $style_block . '</head>', $clean_html, 1 );
        }

        return '<head>' . $style_block . '</head>' . $clean_html;
    }

    /**
     * Retrieve CSS to be applied to PDF documents.
     *
     * @return string
     */
    private static function get_pdf_styles(): string {
        $base_styles = implode(
            '\n',
            array(
                'body.satori-audit-pdf{margin:20mm;font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#1f2933;background:#fff;margin-bottom:72px;}',
                '.satori-audit-pdf img{max-width:100%;height:auto;}',
                '.satori-audit-pdf .satori-audit-report{color:#1f2933;}',
                '.satori-pdf__header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb;}',
                '.satori-pdf__header img{max-height:48px;width:auto;}',
                '.satori-pdf__footer{position:fixed;bottom:0;left:0;right:0;padding:10px 16px;border-top:1px solid #e5e7eb;font-size:12px;color:#4b5563;text-align:center;background:#fff;}',
            )
        );

        return trim( $base_styles . "\n" . self::get_template_styles() );
    }

    /**
     * Load PDF template-specific CSS from disk when available.
     *
     * @return string
     */
    private static function get_template_styles(): string {
        $path = trailingslashit( SATORI_AUDIT_PATH ) . 'assets/css/report-pdf.css';

        if ( is_readable( $path ) ) {
            $css = file_get_contents( $path );

            if ( false !== $css ) {
                return trim( $css );
            }
        }

        return '';
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

        $injected = $html;

        if ( $logo && preg_match( '/<body[^>]*>/i', $injected, $body_match ) ) {
            $replacement = $body_match[0] . $logo;
            $injected    = (string) preg_replace( '/<body[^>]*>/i', $replacement, $injected, 1 );
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
        $font_family = self::normalise_font_family( $settings['pdf_font_family'] );
        $dompdf_ok   = class_exists( Dompdf::class );
        $tcpdf_ok    = class_exists( '\\TCPDF' );

        if ( 'tcpdf' === $preference && ! $tcpdf_ok && $dompdf_ok ) {
            self::log( 'TCPDF requested but unavailable, falling back to DOMPDF.', $settings );
            $preference = 'dompdf';
        }

        if ( 'dompdf' === $preference && $dompdf_ok ) {
            $options = new Options();
            $options->set( 'isRemoteEnabled', true );
            $options->setDefaultFont( $font_family );

            return array(
                'type'     => 'dompdf',
                'instance' => new Dompdf( $options ),
            );
        }

        if ( 'tcpdf' === $preference && $tcpdf_ok ) {
            $tcpdf = new \TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
            $tcpdf->SetCreator( 'Satori Audit' );
            $tcpdf->SetAuthor( get_bloginfo( 'name' ) );
            $tcpdf->setPrintHeader( false );
            $tcpdf->setPrintFooter( false );
            $tcpdf->SetMargins( 15, 18, 15 );
            $tcpdf->SetFont( $font_family, '', 10 );

            return array(
                'type'     => 'tcpdf',
                'instance' => $tcpdf,
            );
        }

        if ( $dompdf_ok ) {
            self::log( 'No preferred engine available; defaulting to DOMPDF.', $settings );

            $options = new Options();
            $options->set( 'isRemoteEnabled', true );
            $options->setDefaultFont( $font_family );

            return array(
                'type'     => 'dompdf',
                'instance' => new Dompdf( $options ),
            );
        }

        if ( $tcpdf_ok ) {
            self::log( 'No preferred engine available; defaulting to TCPDF.', $settings );

            $tcpdf = new \TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
            $tcpdf->SetCreator( 'Satori Audit' );
            $tcpdf->SetAuthor( get_bloginfo( 'name' ) );
            $tcpdf->setPrintHeader( false );
            $tcpdf->setPrintFooter( false );
            $tcpdf->SetMargins( 15, 18, 15 );
            $tcpdf->SetFont( $font_family, '', 10 );

            return array(
                'type'     => 'tcpdf',
                'instance' => $tcpdf,
            );
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
            'display_date_format' => 'j F Y',
            'debug_mode'          => 0,
        );

        $settings = Settings::get_settings();

        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        return array_merge( $defaults, $settings );
    }

    /**
     * Normalize paper size values for supported engines.
     *
     * @param string $paper_size Paper size option.
     * @return string
     */
    private static function normalise_paper_size( string $paper_size ): string {
        $allowed = array( 'A4', 'Letter', 'Legal' );
        $upper   = strtoupper( $paper_size );

        return in_array( $upper, $allowed, true ) ? $upper : 'A4';
    }

    /**
     * Normalize orientation values.
     *
     * @param string $orientation Orientation option.
     * @return string
     */
    private static function normalise_orientation( string $orientation ): string {
        $orientation = strtolower( $orientation );

        return in_array( $orientation, array( 'portrait', 'landscape' ), true ) ? $orientation : 'portrait';
    }

    /**
     * Normalize font family values.
     *
     * @param string $font_family Font option.
     * @return string
     */
    private static function normalise_font_family( string $font_family ): string {
        $font_family = trim( $font_family );

        return $font_family ?: 'Helvetica';
    }

    /**
     * Determine the output path for a generated PDF and ensure directories exist.
     *
     * @param int $report_id Report ID.
     * @return string
     */
    private static function get_pdf_output_path( int $report_id ): string {
        $upload_dir = wp_upload_dir();

        if ( empty( $upload_dir['basedir'] ) ) {
            return '';
        }

        $base_dir = trailingslashit( $upload_dir['basedir'] ) . 'satori-audit/reports/' . $report_id . '/';

        if ( ! wp_mkdir_p( $base_dir ) ) {
            return '';
        }

        $filename = apply_filters( 'satori_audit_pdf_filename', 'satori-audit-report-' . $report_id . '.pdf', $report_id );

        return $base_dir . $filename;
    }
}
