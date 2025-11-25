<?php
/**
 * Report Editor screen controller for Template v2.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Report Editor UI for Template v2.
 */
class Screen_Editor {
    /**
     * Page slug for the editor.
     *
     * @var string
     */
    const PAGE_SLUG = 'satori-audit-report-editor';

    /**
     * Display the editor screen.
     *
     * @return void
     */
    public static function render(): void {
        $settings      = Screen_Settings::get_settings();
        $capabilities  = Screen_Settings::get_capabilities();
        $manage_cap    = $capabilities['manage'];
        $current_user  = get_current_user_id();
        $report_id     = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $template      = isset( $_REQUEST['template'] ) ? sanitize_key( wp_unslash( $_REQUEST['template'] ) ) : 'v2'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $template      = in_array( $template, array( 'v1', 'v2' ), true ) ? $template : 'v2';

        if ( ! current_user_can( $manage_cap ) ) {
            Screen_Settings::log_debug( 'Access denied to Report Editor for user ID ' . $current_user . '.', $settings );
            wp_die( esc_html__( 'You do not have permission to access this page.', 'satori-audit' ) );
        }

        Screen_Settings::log_debug(
            sprintf(
                'Opening Report Editor for report %s using template %s.',
                $report_id ? (string) $report_id : 'new',
                $template
            ),
            $settings
        );

        Screen_Settings::log_debug( 'Template selection set to ' . $template . '.', $settings );

        $error   = '';
        $updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['satori_audit_editor_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            check_admin_referer( 'satori_audit_save_report', 'satori_audit_editor_nonce' );

            $title       = isset( $_POST['report_title'] ) ? sanitize_text_field( wp_unslash( $_POST['report_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $summary     = isset( $_POST['report_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['report_summary'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $report_date = isset( $_POST['report_date'] ) ? sanitize_text_field( wp_unslash( $_POST['report_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $template    = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : $template; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $template    = in_array( $template, array( 'v1', 'v2' ), true ) ? $template : 'v2';

            Screen_Settings::log_debug( 'Template switch requested to ' . $template . '.', $settings );

            $post_data = array(
                'post_title'  => $title ?: __( 'Untitled Report', 'satori-audit' ),
                'post_type'   => 'satori_audit_report',
                'post_status' => 'draft',
            );

            if ( $report_date ) {
                $timestamp = strtotime( $report_date );

                if ( $timestamp ) {
                    $post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $timestamp );
                    $post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
                }
            }

            if ( $report_id ) {
                $post_data['ID'] = $report_id;
                $result          = wp_update_post( $post_data, true );
            } else {
                $post_data['post_author'] = $current_user;
                $result                   = wp_insert_post( $post_data, true );
            }

            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
            } else {
                $report_id = (int) $result;

                update_post_meta( $report_id, '_satori_audit_summary', $summary );
                update_post_meta( $report_id, '_satori_audit_report_date', $report_date );

                Screen_Settings::log_debug( 'Saving report summary for ID ' . $report_id . '.', $settings );

                $redirect = add_query_arg(
                    array(
                        'page'      => self::PAGE_SLUG,
                        'report_id' => $report_id,
                        'template'  => $template,
                        'updated'   => 1,
                    ),
                    admin_url( 'admin.php' )
                );

                wp_safe_redirect( $redirect );
                exit;
            }
        }

        $report      = $report_id ? get_post( $report_id ) : null;
        $title       = $report instanceof \WP_Post ? $report->post_title : '';
        $summary     = $report_id ? (string) get_post_meta( $report_id, '_satori_audit_summary', true ) : ( $summary ?? '' );
        $report_date = $report_id ? (string) get_post_meta( $report_id, '_satori_audit_report_date', true ) : '';

        if ( empty( $report_date ) && $report instanceof \WP_Post ) {
            $report_date = mysql2date( 'Y-m-d', $report->post_date );
        }

        if ( empty( $report_date ) ) {
            $report_date = current_time( 'Y-m-d' );
        }

        echo '<div class="wrap satori-audit-editor-wrap">';
        echo '<div class="satori-audit-editor-topbar">';
        echo '<div class="satori-audit-editor-brand">';
        echo '<span class="satori-audit-badge">SATORI</span>';
        echo '<h1>' . esc_html__( 'Report Editor', 'satori-audit' ) . '</h1>';
        echo '<p class="satori-audit-editor-subtitle">' . esc_html__( 'Template v2 – streamlined metadata editing', 'satori-audit' ) . '</p>';
        echo '</div>';
        echo '<div class="satori-audit-editor-meta">';
        echo '<span class="satori-audit-pill">' . esc_html__( 'Template', 'satori-audit' ) . ': ' . esc_html( strtoupper( $template ) ) . '</span>';
        echo '</div>';
        echo '</div>';

        if ( $updated ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Report details saved.', 'satori-audit' ) . '</p></div>';
        }

        if ( $error ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
        }

        echo '<form method="post" class="satori-audit-editor-form">';
        wp_nonce_field( 'satori_audit_save_report', 'satori_audit_editor_nonce' );
        echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $report_id ) . '" />';
        echo '<input type="hidden" name="template" value="' . esc_attr( $template ) . '" />';

        echo '<div class="satori-audit-editor-grid">';
        echo '<div class="satori-audit-editor-main">';

        echo '<div class="satori-audit-fieldset">';
        echo '<label for="report_title">' . esc_html__( 'Report Title', 'satori-audit' ) . '</label>';
        echo '<input type="text" id="report_title" name="report_title" value="' . esc_attr( $title ) . '" class="widefat" placeholder="' . esc_attr__( 'Monthly Site Audit', 'satori-audit' ) . '" />';
        echo '</div>';

        echo '<div class="satori-audit-fieldset">';
        echo '<label for="report_summary">' . esc_html__( 'Summary', 'satori-audit' ) . '</label>';
        echo '<textarea id="report_summary" name="report_summary" rows="6" class="widefat" placeholder="' . esc_attr__( 'Brief overview of findings and actions...', 'satori-audit' ) . '">';
        echo esc_textarea( $summary );
        echo '</textarea>';
        echo '<p class="description">' . esc_html__( 'Provide a concise summary for this report.', 'satori-audit' ) . '</p>';
        echo '</div>';

        echo '<div class="satori-audit-fieldset">';
        echo '<label>' . esc_html__( 'Additional Metadata', 'satori-audit' ) . '</label>';
        echo '<div class="satori-audit-metadata-stub">';
        echo '<p>' . esc_html__( 'Future-ready block for checklist items, notes, or attachments.', 'satori-audit' ) . '</p>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // main.

        echo '<aside class="satori-audit-editor-sidebar">';
        echo '<div class="satori-audit-panel">';
        echo '<h2>' . esc_html__( 'Report Details', 'satori-audit' ) . '</h2>';
        echo '<label for="report_date">' . esc_html__( 'Report Date', 'satori-audit' ) . '</label>';
        echo '<input type="date" id="report_date" name="report_date" value="' . esc_attr( $report_date ) . '" />';
        echo '<p class="description">' . esc_html__( 'Defaults to the post date. Used in Template v2 headers.', 'satori-audit' ) . '</p>';

        echo '<label for="template_version">' . esc_html__( 'Template Version', 'satori-audit' ) . '</label>';
        echo '<select id="template_version" name="template">';
        echo '<option value="v1"' . selected( $template, 'v1', false ) . '>' . esc_html__( 'Template v1 (legacy)', 'satori-audit' ) . '</option>';
        echo '<option value="v2"' . selected( $template, 'v2', false ) . '>' . esc_html__( 'Template v2 (new)', 'satori-audit' ) . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Switching templates will adjust the preview layout.', 'satori-audit' ) . '</p>';
        echo '</div>';

        echo '<div class="satori-audit-panel">';
        echo '<p class="description">' . esc_html__( 'Save your changes to update the report metadata.', 'satori-audit' ) . '</p>';
        echo '<button type="submit" class="button button-primary button-large">' . esc_html__( 'Save Report', 'satori-audit' ) . '</button>';
        echo '</div>';
        echo '</aside>';
        echo '</div>'; // grid.

        echo '</form>';

        if ( 'v2' === $template ) {
            Screen_Settings::log_debug( 'Rendering Template v2 preview section.', $settings );
        }

        echo '<div class="satori-audit-editor-preview">';
        echo '<div class="satori-audit-panel">';
        echo '<h2>' . esc_html__( 'Template Preview', 'satori-audit' ) . '</h2>';
        if ( 'v2' === $template ) {
            echo '<div class="satori-audit-preview-v2">';
            echo '<div class="satori-audit-preview-live">';
            echo '<h3 class="preview-title">' . esc_html( $title ?: __( 'Report Title', 'satori-audit' ) ) . '</h3>';
            echo '<p class="preview-date">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $report_date ) ) ) . '</p>';
            echo '<div class="preview-summary">' . wpautop( esc_html( $summary ?: __( 'Summary content will appear here.', 'satori-audit' ) ) ) . '</div>';
            echo '</div>';
            echo '<div class="satori-audit-preview-partials">';
            include SATORI_AUDIT_PATH . 'templates/report-v2/header.php';
            include SATORI_AUDIT_PATH . 'templates/report-v2/summary.php';
            include SATORI_AUDIT_PATH . 'templates/report-v2/plugin-updates.php';
            include SATORI_AUDIT_PATH . 'templates/report-v2/footer.php';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'Template v1 preview not available in this UI. Switch back to Template v2 for the new layout.', 'satori-audit' ) . '</p>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }
}
