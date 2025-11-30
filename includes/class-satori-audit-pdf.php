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

			/* -------------------------------------------------
			 * TEMP / DEV: snapshot last HTML sent to PDF engine
			 * -------------------------------------------------*/
			self::dump_last_html( $html );

			if ( empty( trim( $html ) ) ) {
				self::log( 'PDF generation failed for report ' . $report_id . ': empty HTML.', $settings );

				return '';
			}

			if ( self::is_debug_mode( $settings ) ) {
				self::output_debug_html( $html );
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
		$content  = self::prepare_report_content( $html );
		$prepared = self::build_pdf_html( $content );
		$prepared = self::apply_header_footer( $prepared, $settings );

		return self::absolutize_urls( $prepared );
	}

	/* -------------------------------------------------
	 * Section: PDF HTML assembly and CSS injection
	 * -------------------------------------------------*/

	/**
	 * Assemble the final PDF document shell.
	 *
	 * @param string $report_html Report markup without style tags.
	 * @return string
	 */
	private static function build_pdf_html( string $report_html ): string {
		$html  = '<!DOCTYPE html><html><head>';
		$html .= '<meta charset="utf-8">';
		$html .= '<style>' . self::get_pdf_styles() . '</style>';
		$html .= '</head><body class="satori-audit-pdf">';
		$html .= '<div class="satori-audit-report satori-audit-report-pdf">';
		$html .= $report_html;
		$html .= '</div></body></html>';

		return $html;
	}

	/**
	 * Normalise report markup for PDF rendering.
	 *
	 * @param string $html Raw report HTML.
	 * @return string
	 */
	private static function prepare_report_content( string $html ): string {
		$content = self::extract_body_content( $html );
		$content = self::strip_style_tags( $content );

		return str_replace( 'satori-audit-report-preview', 'satori-audit-report-pdf', $content );
	}

	/**
	 * Extract body contents when a full document is provided.
	 *
	 * @param string $html Raw report HTML.
	 * @return string
	 */
	private static function extract_body_content( string $html ): string {
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) {
			return trim( $matches[1] );
		}

		if ( preg_match( '/<html[^>]*>(.*?)<\/html>/is', $html, $matches ) ) {
			return trim( $matches[1] );
		}

		return trim( $html );
	}

	/**
	 * Remove inline style tags from report markup.
	 *
	 * @param string $html Report HTML.
	 * @return string
	 */
	private static function strip_style_tags( string $html ): string {
		return (string) preg_replace( '/<style[^>]*>.*?<\/style>/is', '', $html );
	}

	/**
	 * Retrieve CSS to be applied to PDF documents.
	 *
	 * @return string
	 */
	private static function get_pdf_styles(): string {
		$base_styles = implode(
			"\n",
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
                $raw_preference = (string) $settings['pdf_engine'];
                $preference     = self::normalise_engine_preference( $raw_preference );

                if ( $preference !== $raw_preference ) {
                        self::log( 'Unknown PDF engine preference "' . $raw_preference . '" normalised to automatic.', $settings );
                }

                self::log( 'Attempting to load PDF engine: ' . $preference, $settings );

                if ( 'none' === $preference ) {
                        self::log( 'PDF engine is disabled via settings.', $settings );

                        return array();
                }

                $tcpdf = self::init_tcpdf( $settings );
                $dompdf = self::init_dompdf( $settings );

                if ( 'automatic' === $preference ) {
                        if ( ! empty( $tcpdf ) ) {
                                self::log( 'Automatic mode selected TCPDF.', $settings );

                                return $tcpdf;
                        }

                        if ( ! empty( $dompdf ) ) {
                                self::log( 'Automatic mode falling back to DOMPDF.', $settings );

                                return $dompdf;
                        }
                }

                if ( 'tcpdf' === $preference ) {
                        if ( ! empty( $tcpdf ) ) {
                                return $tcpdf;
                        }

                        self::log( 'TCPDF requested but unavailable; attempting DOMPDF fallback.', $settings );

                        if ( ! empty( $dompdf ) ) {
                                return $dompdf;
                        }
                }

                if ( 'dompdf' === $preference ) {
                        if ( ! empty( $dompdf ) ) {
                                return $dompdf;
                        }

                        self::log( 'DOMPDF requested but unavailable; attempting TCPDF fallback.', $settings );

                        if ( ! empty( $tcpdf ) ) {
                                return $tcpdf;
                        }
                }

                if ( ! empty( $tcpdf ) ) {
                        self::log( 'No preferred engine available; defaulting to TCPDF.', $settings );

                        return $tcpdf;
                }

                if ( ! empty( $dompdf ) ) {
                        self::log( 'No preferred engine available; defaulting to DOMPDF.', $settings );

                        return $dompdf;
                }

                self::log( 'No suitable PDF engine available after all attempts.', $settings );

                return array();
        }

        /**
         * Initialize the TCPDF engine when present.
         *
         * @param array $settings Plugin settings.
         * @return array{type:string,instance:object}|array
         */
        private static function init_tcpdf( array $settings ): array {
                $font_family = self::normalise_font_family( $settings['pdf_font_family'] );

                self::maybe_include_tcpdf( $settings );

                if ( ! class_exists( '\\TCPDF' ) ) {
                        self::log( 'TCPDF class not found after bootstrap attempt.', $settings );

                        return array();
                }

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

        /**
         * Initialize the DOMPDF engine when present.
         *
         * @param array $settings Plugin settings.
         * @return array{type:string,instance:object}|array
         */
        private static function init_dompdf( array $settings ): array {
                $font_family = self::normalise_font_family( $settings['pdf_font_family'] );

                if ( ! class_exists( Dompdf::class ) ) {
                        self::log( 'DOMPDF class not found.', $settings );

                        return array();
                }

                $options = new Options();
                $options->set( 'isRemoteEnabled', true );
                $options->setDefaultFont( $font_family );

                return array(
                        'type'     => 'dompdf',
                        'instance' => new Dompdf( $options ),
                );
        }

        /**
         * Attempt to bootstrap TCPDF from Composer or the bundled fallback.
         *
         * @param array $settings Plugin settings.
         * @return void
         */
        private static function maybe_include_tcpdf( array $settings ): void {
                if ( class_exists( '\\TCPDF' ) ) {
                        return;
                }

                $paths = array(
                        trailingslashit( SATORI_AUDIT_PATH ) . 'vendor/tecnickcom/tcpdf/tcpdf.php',
                        trailingslashit( SATORI_AUDIT_PATH ) . 'includes/vendor/tcpdf/tcpdf.php',
                );

                foreach ( $paths as $path ) {
                        if ( is_readable( $path ) ) {
                                self::log( 'Attempting to load TCPDF from ' . $path, $settings );
                                require_once $path;

                                if ( class_exists( '\\TCPDF' ) ) {
                                        return;
                                }
                        }
                }
        }

        /**
         * Normalise the engine preference value.
         *
         * @param string $preference Raw preference value.
         * @return string
         */
        private static function normalise_engine_preference( string $preference ): string {
                $allowed = array( 'automatic', 'tcpdf', 'dompdf', 'none' );

                if ( in_array( $preference, $allowed, true ) ) {
                        return $preference;
                }

                return 'automatic';
        }

	/* -------------------------------------------------
	 * PDF Debug Mode: helper
	 * -------------------------------------------------*/
	protected static function is_debug_mode( array $settings ): bool {

		// Developer override (e.g., wp-config.php).
		if ( defined( 'SATORI_AUDIT_PDF_DEBUG' ) && true === SATORI_AUDIT_PDF_DEBUG ) {
			return is_user_logged_in() && current_user_can( 'manage_options' );
		}

		$enabled = ! empty( $settings['pdf_debug_html'] );

		// Only admins can use debug mode.
		return $enabled
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
			&& is_admin();
	}

	/**
	 * Output assembled HTML when debug mode is enabled.
	 *
	 * @param string $html Fully assembled HTML document.
	 * @return void
	 */
	private static function output_debug_html( string $html ): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		echo "<!-- SATORI Audit PDF Debug Mode: HTML output only -->";
		echo $html;
		exit;
	}

	/* -------------------------------------------------
	 * HTML snapshot helper (for local debugging)
	 * -------------------------------------------------*/

	/**
	 * Dump the last HTML sent to the PDF engine to uploads/satori-audit-pdf-last.html.
	 *
	 * Only active when WP_DEBUG is true to avoid cluttering production systems.
	 *
	 * @param string $html Fully assembled HTML document.
	 * @return void
	 */
	private static function dump_last_html( string $html ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$upload_dir = wp_upload_dir();

		if ( empty( $upload_dir['basedir'] ) ) {
			return;
		}

		$path = trailingslashit( $upload_dir['basedir'] ) . 'satori-audit-pdf-last.html';

		// Suppress errors – this is a best-effort debug helper.
		@file_put_contents( $path, $html );
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
                        'pdf_engine'          => 'automatic',
			'pdf_paper_size'      => 'A4',
			'pdf_orientation'     => 'portrait',
			'pdf_font_family'     => 'Helvetica',
			'pdf_logo_url'        => '',
			'pdf_footer_text'     => '',
			'pdf_debug_html'      => 0,
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
