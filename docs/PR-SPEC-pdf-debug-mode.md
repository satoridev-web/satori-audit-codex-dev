    # PR-SPEC – PDF Debug Mode (HTML instead of PDF)
    *Slug: pdf-debug-mode*  
    *Plugin: SATORI Audit*  

    ---

    ## 1. Background

    While working on the PDF CSS integration for DOMPDF (PR #32 and successors),
    we currently cannot easily see the **exact HTML** that is being passed into
    DOMPDF. Sometimes only a single line of CSS appears in the PDF output,
    making it unclear whether the issue lies in the wrapper HTML, injected CSS,
    or DOMPDF parsing limitations.

    A proven pattern used by other plugins (e.g. WooCommerce PDF Catalog)
    is to provide an **Enable Debug Mode** option that bypasses PDF generation
    and outputs the raw HTML directly. This improves troubleshooting, assists
    development work, and is particularly valuable for commercial/pro versions.

    This PR introduces the same functionality into SATORI Audit.

    ---

    ## 2. Goals

    1. Add a **PDF Debug Mode** toggle in the SATORI Audit Settings screen.
    2. When enabled (and the requesting user has admin permission):
       - Skip DOMPDF rendering entirely.
       - Output the final HTML that would normally be passed to DOMPDF.
       - Terminate the request safely.
    3. Ensure:
       - Only administrative users can trigger debug output.
       - Normal users always receive PDF output.
    4. Maintain a clean, secure, production-ready implementation.

    ---

    ## 3. Scope

    ### 3.1 In Scope
    - Adding a new settings checkbox to the SATORI Audit settings page.
    - Extending the settings option array (`satori_audit_settings`) to store
      the boolean value.
    - Adding a debug helper method to the PDF class.
    - Adding debug-mode HTML output logic into the PDF generation flow.
    - Support for a developer override constant:
      `SATORI_AUDIT_PDF_DEBUG`.

    ### 3.2 Out of Scope
    - Template v2 CSS injection logic (covered under separate PR specifications).
    - Report templates and preview logic.
    - Archive functionality and scheduling/automation.
    - Any UI changes outside the settings screen.

    ---

    ## 4. Implementation Requirements

    ### 4.1 Settings Storage

    Store the debug toggle inside the existing `satori_audit_settings` option:

    ```php
    $settings['pdf_debug_html'] = '1' or '0';
    ```

    No new WordPress options should be created.

    ### 4.2 Admin UI

    Add a checkbox field to the Settings screen (Screen_Settings or equivalent):

    ```php
    <tr>
        <th scope="row">
            <label for="satori_audit_pdf_debug_html">
                <?php esc_html_e( 'PDF debug mode', 'satori-audit' ); ?>
            </label>
        </th>
        <td>
            <label>
                <input type="checkbox"
                       name="satori_audit_settings[pdf_debug_html]"
                       id="satori_audit_pdf_debug_html"
                       value="1"
                       <?php checked( $pdf_debug_html ); ?> />
                <?php esc_html_e( 'Enable PDF debug mode (output raw HTML instead of PDF for administrators).', 'satori-audit' ); ?>
            </label>
            <p class="description">
                <?php esc_html_e( 'For development and troubleshooting only. This bypasses DOMPDF and outputs the HTML intended for PDF generation.', 'satori-audit' ); ?>
            </p>
        </td>
    </tr>
    ```

    The existing form submit handler should already handle saving this value.

    ### 4.3 Debug Helper Method

    In the PDF generator class (e.g. `class-satori-audit-pdf.php`), add:

    ```php
    /* -------------------------------------------------
     * PDF Debug Mode: helper
     * -------------------------------------------------*/
    protected function is_debug_mode(): bool {

        // Developer override (e.g., wp-config.php).
        if ( defined( 'SATORI_AUDIT_PDF_DEBUG' ) && true === SATORI_AUDIT_PDF_DEBUG ) {
            return is_user_logged_in() && current_user_can( 'manage_options' );
        }

        $settings = get_option( 'satori_audit_settings', array() );
        $enabled  = ! empty( $settings['pdf_debug_html'] );

        // Only admins can use debug mode.
        return $enabled
            && is_user_logged_in()
            && current_user_can( 'manage_options' )
            && is_admin();
    }
    ```

    ### 4.4 Debug Output Hook (Critical Logic)

    Before invoking DOMPDF:

    ```php
    /* -------------------------------------------------
     * Optional Debug Output (HTML instead of PDF)
     * -------------------------------------------------*/
    if ( $this->is_debug_mode() ) {

        if ( ! headers_sent() ) {
            header( 'Content-Type: text/html; charset=utf-8' );
        }

        echo "<!-- SATORI Audit PDF Debug Mode: HTML output only -->
";
        echo $html; // Must contain the fully assembled wrapper and content.
        exit;
    }
    ```

    Insert this after `$html` is assembled and before:

    ```php
    $dompdf->loadHtml( $html );
    ```

    ### 4.5 Developer Override (Optional)

    Support constant:

    ```php
    define( 'SATORI_AUDIT_PDF_DEBUG', true );
    ```

    When set to `true`, debug mode activates regardless of stored settings,
    subject to capability checks.

    When set to `false`, stored settings apply normally.

    ---

    ## 5. Behaviour & UX

    ### Debug Mode OFF (default)
    - PDFs are generated normally.
    - No visible UI change other than the checkbox.

    ### Debug Mode ON
    - Admin → Export PDF:
      - Browser displays full HTML document (wrapper + CSS + content).
      - No PDF download.
    - Non-admin:
      - Always receives normal PDF output.

    ---

    ## 6. Testing & Acceptance Criteria

    ### 6.1 Manual Tests

    1. **Default Case**
       - Debug disabled.
       - Admin exports PDF → PDF downloads normally.

    2. **Toggle Enabled**
       - Enable “PDF debug mode”.
       - Admin exports PDF → HTML renders in browser.
       - Confirm HTML includes `<html>`, `<head>`, `<style>`, `<body>`.

    3. **Constant Override**
       - Define: `define( 'SATORI_AUDIT_PDF_DEBUG', true );`
       - Admin exports PDF → HTML output even if checkbox is off.

    4. **Permission Test**
       - Non-admin user attempts export.
       - Always receives PDF, even if debug mode is enabled.

    ### 6.2 Acceptance Criteria

    - [ ] PDF Debug Mode checkbox appears and saves properly.
    - [ ] HTML output replaces PDF only for eligible users.
    - [ ] No output leakage to frontend.
    - [ ] Normal PDF behaviour unchanged when debug is off.
    - [ ] PHPCS and CI pipelines pass.

    ---

    ## 7. Notes

    - Follow SATORI code organisation and commenting patterns:

      ```php
      /* -------------------------------------------------
       * Section: PDF Debug Mode
       * -------------------------------------------------*/
      ```

    - This feature is intended as a permanent development utility for future
      commercial versions of SATORI Audit.

    ---
