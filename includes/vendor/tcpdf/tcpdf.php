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
                $content = "%PDF-1.4\\n% Stub TCPDF output\\n" . ( $this->lastHtml ?? '' );

                if ( 'S' === $dest ) {
                        return $content;
                }

                echo $content;

                return $content;
        }
}
