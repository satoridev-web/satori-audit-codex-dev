<?php
namespace Dompdf;

/**
 * Minimal DOMPDF-compatible renderer for offline use.
 */
class Dompdf {
    /**
     * @var Options
     */
    private Options $options;

    /**
     * @var string
     */
    private string $html = '';

    /**
     * @var string
     */
    private string $paper = 'a4';

    /**
     * @var string
     */
    private string $orientation = 'portrait';

    /**
     * @var string
     */
    private string $rendered = '';

    /**
     * Constructor.
     *
     * @param Options|null $options Options instance.
     */
    public function __construct( ?Options $options = null ) {
        $this->options = $options ?? new Options();
    }

    /**
     * Load HTML content for rendering.
     *
     * @param string $html HTML markup.
     * @return void
     */
    public function loadHtml( string $html ): void {
        $this->html = $html;
    }

    /**
     * Configure paper size and orientation.
     *
     * @param string $size        Paper size.
     * @param string $orientation Orientation.
     * @return void
     */
    public function setPaper( string $size, string $orientation = 'portrait' ): void {
        $this->paper       = strtolower( $size );
        $this->orientation = strtolower( $orientation );
    }

    /**
     * Render the loaded HTML to PDF.
     *
     * @return void
     */
    public function render(): void {
        $this->rendered = $this->build_pdf();
    }

    /**
     * Retrieve the rendered PDF output.
     *
     * @return string
     */
    public function output(): string {
        return $this->rendered;
    }

    /**
     * Build a simple PDF payload containing the rendered text content.
     *
     * @return string
     */
    private function build_pdf(): string {
        $text = html_entity_decode( trim( strip_tags( $this->html ) ), ENT_QUOTES, 'UTF-8' );
        $text = preg_replace( '/\s+/', ' ', $text ) ?? '';
        $text = wordwrap( $text, 90, "\n", true );

        $dimensions = $this->paper_dimensions();
        $width      = $dimensions['width'];
        $height     = $dimensions['height'];
        if ( 'landscape' === $this->orientation ) {
            $temp    = $width;
            $width   = $height;
            $height  = $temp;
        }

        $font = $this->options->get( 'default_font', 'Helvetica' );

        $lines = explode( "\n", $text );
        $yPos  = $height - 72;
        $leading = 14;
        $content = "BT\n/F1 12 Tf\n";
        foreach ( $lines as $line ) {
            $safeLine = $this->escape_text( $line );
            $content .= sprintf( "50 %.2f Td (%s) Tj\n", $yPos, $safeLine );
            $content .= "0 -$leading Td\n";
            $yPos    -= $leading;
        }
        $content .= "ET";

        $objects = array();
        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
        $objects[] = "2 0 obj<< /Type /Pages /Count 1 /Kids [3 0 R] >>endobj";
        $objects[] = sprintf(
            "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj",
            $width,
            $height
        );
        $objects[] = sprintf(
            "4 0 obj<< /Length %d >>stream\n%s\nendstream endobj",
            strlen( $content ),
            $content
        );
        $objects[] = sprintf(
            "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /%s >>endobj",
            $this->sanitize_font( $font )
        );

        $pdf   = "%PDF-1.4\n";
        $xref  = array();
        $offset = strlen( $pdf );

        foreach ( $objects as $index => $object ) {
            $xref[] = $offset;
            $pdf   .= $object . "\n";
            $offset = strlen( $pdf );
        }

        $xrefStart = strlen( $pdf );
        $pdf      .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
        $pdf      .= "0000000000 65535 f \n";
        foreach ( $xref as $ref ) {
            $pdf .= sprintf( "%010d 00000 n \n", $ref );
        }
        $pdf .= "trailer<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";

        return $pdf;
    }

    /**
     * Escape PDF text content.
     *
     * @param string $text Raw text.
     * @return string
     */
    private function escape_text( string $text ): string {
        return strtr( $text, array( '\\' => '\\\\', '(' => '\\(', ')' => '\\)' ) );
    }

    /**
     * Sanitize font values for built-in PDF font selection.
     *
     * @param string $font Font name.
     * @return string
     */
    private function sanitize_font( string $font ): string {
        $font = preg_replace( '/[^A-Za-z0-9_-]/', '', $font ) ?? 'Helvetica';

        return $font ?: 'Helvetica';
    }

    /**
     * Map paper sizes to dimensions in points.
     *
     * @return array{width:float,height:float}
     */
    private function paper_dimensions(): array {
        $sizes = array(
            'a4'     => array( 'width' => 595.28, 'height' => 841.89 ),
            'letter' => array( 'width' => 612.0, 'height' => 792.0 ),
            'legal'  => array( 'width' => 612.0, 'height' => 1008.0 ),
        );

        return $sizes[ $this->paper ] ?? $sizes['a4'];
    }
}
