<?php
/**
 * Minimal TCPDF compatibility layer bundled with SATORI Audit.
 *
 * This lightweight implementation mirrors a handful of TCPDF methods used by
 * the plugin so PDF generation continues to function when Composer installs
 * are unavailable. It is not a full TCPDF distribution.
 */

if ( class_exists( '\\TCPDF' ) ) {
        return;
}

class TCPDF {
        public function __construct( $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false ) {
                // Minimal constructor provided for API compatibility.
        }

        public function SetCreator( $creator ) {
                // No-op for compatibility.
        }

        public function SetAuthor( $author ) {
                // No-op for compatibility.
        }

        public function setPrintHeader( $print ) {
                // No-op for compatibility.
        }

        public function setPrintFooter( $print ) {
                // No-op for compatibility.
        }

        public function SetMargins( $left, $top, $right ) {
                // No-op for compatibility.
        }

        public function SetFont( $family, $style = '', $size = null ) {
                // No-op for compatibility.
        }

        public function AddPage( $orientation = '', $format = '' ) {
                // No-op for compatibility.
        }

        public function writeHTML( $html, $ln = true, $fill = false, $reseth = true, $cell = false, $align = '' ) {
                $this->lastHtml = (string) $html;
        }

        public function Output( $name = '', $dest = 'I' ) {
                $text = isset( $this->lastHtml ) ? $this->lastHtml : '';

                $plain = function_exists( '\\wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
                $plain = trim( (string) $plain );
                $plain = '' === $plain ? 'SATORI Audit PDF content unavailable.' : $plain;

                $content = $this->build_minimal_pdf( $plain );

                if ( 'S' === $dest ) {
                        return $content;
                }

                echo $content;

                return $content;
        }

        /**
         * Build a minimal but valid PDF payload.
         *
         * @param string $text Text to display in the document.
         * @return string
         */
        private function build_minimal_pdf( string $text ): string {
                $escaped = str_replace(
                        array( '\\', '(', ')' ),
                        array( '\\\\', '\\(', '\\)' ),
                        $text
                );

                $objects   = array();
                $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
                $objects[] = "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj\n";
                $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

                $stream    = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";
                $length    = strlen( $stream );
                $objects[] = "4 0 obj\n<< /Length {$length} >>\nstream\n{$stream}\nendstream\nendobj\n";
                $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

                $pdf     = "%PDF-1.4\n";
                $offsets = array( 0 );

                foreach ( $objects as $object ) {
                        $offsets[] = strlen( $pdf );
                        $pdf      .= $object;
                }

                $xref_position = strlen( $pdf );
                $count         = count( $offsets );

                $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

                for ( $i = 1; $i < $count; $i++ ) {
                        $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
                }

                $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref_position}\n%%EOF";

                return $pdf;
        }
}
