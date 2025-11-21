<?php
/**
 * Dashboard admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

use Satori_Audit\Includes\Satori_Audit_Plugin;
use Satori_Audit\Includes\Satori_Audit_Pdf;
use Satori_Audit\Includes\Satori_Audit_Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Dashboard page for SATORI Audit.
 */
class Satori_Audit_Screen_Dashboard {
    /**
     * Report service.
     */
    private Satori_Audit_Reports $reports;

    /**
     * Constructor wires admin-post actions.
     */
    public function __construct() {
        $this->reports = new Satori_Audit_Reports();

        add_action( 'admin_post_satori_audit_generate_report', [ $this, 'handle_generate' ] );
        add_action( 'admin_post_satori_audit_save_report', [ $this, 'handle_save_report' ] );
        add_action( 'admin_post_satori_audit_export_csv', [ $this, 'handle_export_csv' ] );
        add_action( 'admin_post_satori_audit_export_pdf', [ $this, 'handle_export_pdf' ] );
        add_action( 'admin_post_satori_audit_lock_report', [ $this, 'handle_lock' ] );
        add_action( 'admin_post_satori_audit_unlock_report', [ $this, 'handle_unlock' ] );
    }

    /**
     * Display dashboard content.
     */
    public function render(): void {
        $settings = Satori_Audit_Plugin::get_settings();
        $period   = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : gmdate( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $report   = $this->reports->get_report_id_by_period( $period );

        if ( ! $report ) {
            $report = $this->reports->generate_report( $period );
        }

        $plugin_rows   = $this->reports->get_plugin_rows( $report );
        $security_rows = $this->reports->get_security_rows( $report );
        $meta          = $this->reports->get_report_meta( $report );
        $summary       = $this->reports->get_summary( $report );
        $locked        = $this->reports->is_locked( $report );

        echo '<div class="wrap satori-audit-wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Dashboard', 'satori-audit' ) . '</h1>';
        $this->render_toolbar( $period, $report, $locked );
        echo '<div class="satori-audit-grid">';
        echo '<div class="satori-audit-panel">';
        $this->render_report_editor( $report, $period, $plugin_rows, $security_rows, $meta, $locked );
        echo '</div>';
        echo '<div class="satori-audit-panel">';
        $this->render_preview( $report, $period, $plugin_rows, $security_rows, $meta, $summary, $settings );
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Toolbar with actions.
     */
    private function render_toolbar( string $period, int $report_id, bool $locked ): void {
        $nonce = wp_create_nonce( 'satori_audit_actions' );

        echo '<div class="satori-audit-toolbar">';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="satori_audit_generate_report" />';
        echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
        submit_button( __( 'Generate/Refresh Current Month', 'satori-audit' ), 'primary', 'submit', false );
        echo '</form>';

        if ( $locked ) {
            $this->lock_button( 'unlock', __( 'Unlock', 'satori-audit' ), $period );
        } else {
            $this->lock_button( 'lock', __( 'Lock', 'satori-audit' ), $period );
        }

        $this->export_button( 'csv', __( 'Export CSV', 'satori-audit' ), $report_id, $period );
        $this->export_button( 'pdf', __( 'Export PDF', 'satori-audit' ), $report_id, $period );
        echo '</div>';
    }

    /**
     * Helper for lock/unlock button form.
     */
    private function lock_button( string $action, string $label, string $period ): void {
        $nonce = wp_create_nonce( 'satori_audit_actions' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="satori_audit_' . esc_attr( $action ) . '_report" />';
        echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
        submit_button( $label, '', 'submit', false );
        echo '</form>';
    }

    /**
     * Helper for export buttons.
     */
    private function export_button( string $type, string $label, int $report_id, string $period ): void {
        $nonce = wp_create_nonce( 'satori_audit_actions' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank">';
        echo '<input type="hidden" name="action" value="satori_audit_export_' . esc_attr( $type ) . '" />';
        echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $report_id ) . '" />';
        echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
        submit_button( $label, 'secondary', 'submit', false );
        echo '</form>';
    }

    /**
     * Render report editor forms for overview, plugins, and security.
     */
    private function render_report_editor( int $report_id, string $period, array $plugin_rows, array $security_rows, array $meta, bool $locked ): void {
        echo '<h2>' . esc_html__( 'Report Editor', 'satori-audit' ) . '</h2>';
        echo '<p>' . esc_html__( 'Edit overview notes, plugin classifications, and security findings. Save to update the preview and exports.', 'satori-audit' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="satori-audit-editor">';
        wp_nonce_field( 'satori_audit_save_report', '_wpnonce' );
        echo '<input type="hidden" name="action" value="satori_audit_save_report" />';
        echo '<input type="hidden" name="report_id" value="' . esc_attr( (string) $report_id ) . '" />';
        echo '<input type="hidden" name="period" value="' . esc_attr( $period ) . '" />';

        if ( $locked ) {
            echo '<p class="notice notice-warning"><em>' . esc_html__( 'This report is locked. Unlock to edit.', 'satori-audit' ) . '</em></p>';
        }

        $this->render_overview_fields( $meta );
        $this->render_plugin_table( $plugin_rows, $locked );
        $this->render_security_table( $security_rows, $locked );

        submit_button( __( 'Save Report', 'satori-audit' ), 'primary', 'submit', false, [ 'disabled' => $locked ? 'disabled' : '' ] );
        echo '</form>';
    }

    /**
     * Overview text fields.
     */
    private function render_overview_fields( array $meta ): void {
        $overview = $meta['overview'] ?? [ 'security' => '', 'general' => '', 'misc' => '', 'comments' => '' ];
        echo '<div class="satori-audit-fieldset">';
        echo '<h3>' . esc_html__( 'Overview', 'satori-audit' ) . '</h3>';
        foreach ( $overview as $key => $value ) {
            echo '<label>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</label>';
            printf( '<textarea name="overview[%1$s]" rows="2">%2$s</textarea>', esc_attr( $key ), esc_textarea( (string) $value ) );
        }
        echo '</div>';
    }

    /**
     * Plugin grid table.
     */
    private function render_plugin_table( array $plugin_rows, bool $locked ): void {
        echo '<div class="satori-audit-fieldset">';
        echo '<h3>' . esc_html__( 'Plugins', 'satori-audit' ) . '</h3>';
        echo '<table class="widefat striped satori-audit-table">';
        echo '<thead><tr>';
        $headers = [ 'Status', 'Slug', 'Name', 'Type', 'Version', 'From', 'To', 'Active', 'Price Notes', 'Comments' ];
        foreach ( $headers as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( empty( $plugin_rows ) ) {
            echo '<tr><td colspan="10" class="empty">' . esc_html__( 'No plugin data captured yet.', 'satori-audit' ) . '</td></tr>';
        } else {
            foreach ( $plugin_rows as $index => $row ) {
                echo '<tr>';
                printf( '<td><input type="hidden" name="status_flag[%1$d]" value="%2$s" />%2$s</td>', $index, esc_html( $row['status_flag'] ) );
                printf( '<td><input type="text" name="plugin_slug[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['plugin_slug'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="text" name="plugin_name[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['plugin_name'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="text" name="plugin_type[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['plugin_type'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="text" name="version_current[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['version_current'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="text" name="version_from[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['version_from'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="text" name="version_to[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['version_to'] ), disabled( $locked, true, false ) );
                printf( '<td><input type="checkbox" name="is_active[%1$d]" %2$s %3$s /></td>', $index, checked( (int) $row['is_active'], 1, false ), disabled( $locked, true, false ) );
                printf( '<td><textarea name="price_notes[%1$d]" %3$s>%2$s</textarea></td>', $index, esc_textarea( $row['price_notes'] ), disabled( $locked, true, false ) );
                printf( '<td><textarea name="comments[%1$d]" %3$s>%2$s</textarea></td>', $index, esc_textarea( $row['comments'] ), disabled( $locked, true, false ) );
                echo '<input type="hidden" name="plugin_description[' . esc_attr( (string) $index ) . ']" value="' . esc_attr( $row['plugin_description'] ) . '" />';
                echo '<input type="hidden" name="last_checked[' . esc_attr( (string) $index ) . ']" value="' . esc_attr( $row['last_checked'] ) . '" />';
                echo '</tr>';
            }
        }

        // Blank row for manual additions.
        $blank = count( $plugin_rows );
        echo '<tr class="satori-audit-blank">';
        echo '<td><input type="hidden" name="status_flag[' . esc_attr( (string) $blank ) . ']" value="custom" />' . esc_html__( 'Custom', 'satori-audit' ) . '</td>';
        echo '<td><input type="text" name="plugin_slug[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="plugin_name[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="plugin_type[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="version_current[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="version_from[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="version_to[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="checkbox" name="is_active[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><textarea name="price_notes[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . '></textarea></td>';
        echo '<td><textarea name="comments[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . '></textarea></td>';
        echo '<input type="hidden" name="plugin_description[' . esc_attr( (string) $blank ) . ']" value="" />';
        echo '<input type="hidden" name="last_checked[' . esc_attr( (string) $blank ) . ']" value="' . esc_attr( current_time( 'mysql', true ) ) . '" />';
        echo '</tr>';

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Security/known issues table.
     */
    private function render_security_table( array $security_rows, bool $locked ): void {
        echo '<div class="satori-audit-fieldset">';
        echo '<h3>' . esc_html__( 'Security & Known Issues', 'satori-audit' ) . '</h3>';
        echo '<table class="widefat striped satori-audit-table">';
        echo '<thead><tr>'; 
        $headers = [ 'Type', 'Description', 'CVSS', 'Severity', 'Attack Report', 'Solution', 'Action Required' ];
        foreach ( $headers as $header ) {
            echo '<th>' . esc_html( $header ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( empty( $security_rows ) ) {
            echo '<tr><td colspan="7" class="empty">' . esc_html__( 'No security notes recorded yet.', 'satori-audit' ) . '</td></tr>';
        }

        foreach ( $security_rows as $index => $row ) {
            echo '<tr>';
            printf( '<td><input type="text" name="vulnerability_type[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['vulnerability_type'] ), disabled( $locked, true, false ) );
            printf( '<td><textarea name="description[%1$d]" %3$s>%2$s</textarea></td>', $index, esc_textarea( $row['description'] ), disabled( $locked, true, false ) );
            printf( '<td><input type="text" name="cvss_score[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['cvss_score'] ), disabled( $locked, true, false ) );
            printf( '<td><input type="text" name="severity[%1$d]" value="%2$s" %3$s /></td>', $index, esc_attr( $row['severity'] ), disabled( $locked, true, false ) );
            printf( '<td><textarea name="attack_report[%1$d]" %3$s>%2$s</textarea></td>', $index, esc_textarea( $row['attack_report'] ?? '' ), disabled( $locked, true, false ) );
            printf( '<td><textarea name="solution[%1$d]" %3$s>%2$s</textarea></td>', $index, esc_textarea( $row['solution'] ?? '' ), disabled( $locked, true, false ) );
            printf( '<td class="center"><input type="checkbox" name="action_required[%1$d]" %2$s %3$s /></td>', $index, checked( (int) ( $row['action_required'] ?? 0 ), 1, false ), disabled( $locked, true, false ) );
            echo '</tr>';
        }

        $blank = count( $security_rows );
        echo '<tr class="satori-audit-blank">';
        echo '<td><input type="text" name="vulnerability_type[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><textarea name="description[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . '></textarea></td>';
        echo '<td><input type="text" name="cvss_score[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><input type="text" name="severity[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '<td><textarea name="attack_report[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . '></textarea></td>';
        echo '<td><textarea name="solution[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . '></textarea></td>';
        echo '<td class="center"><input type="checkbox" name="action_required[' . esc_attr( (string) $blank ) . ']" ' . disabled( $locked, true, false ) . ' /></td>';
        echo '</tr>';

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Render preview pane.
     */
    private function render_preview( int $report_id, string $period, array $plugin_rows, array $security_rows, array $meta, array $summary, array $settings ): void {
        echo '<h2>' . esc_html__( 'HTML Preview', 'satori-audit' ) . '</h2>';
        echo '<p>' . esc_html__( 'Live BALL-style preview used for PDF and CSV exports.', 'satori-audit' ) . '</p>';
        $report_id  = $report_id; // Clarity for template include.
        $period     = $period;
        $plugin_rows = $plugin_rows;
        $security_rows = $security_rows;
        $meta        = $meta;
        $summary     = $summary;
        $settings    = $settings;

        include SATORI_AUDIT_PLUGIN_DIR . 'templates/admin/report-preview.php';
    }

    /**
     * Handle manual generate action.
     */
    public function handle_generate(): void {
        check_admin_referer( 'satori_audit_actions' );
        $period = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : gmdate( 'Y-m' );
        $this->reports->generate_report( $period, true );
        $this->redirect_with_notice( __( 'Report generated.', 'satori-audit' ) );
    }

    /**
     * Handle report save.
     */
    public function handle_save_report(): void {
        check_admin_referer( 'satori_audit_save_report' );
        $report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;
        $period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : gmdate( 'Y-m' );

        if ( $this->reports->is_locked( $report_id ) ) {
            $this->redirect_with_notice( __( 'Report is locked. Unlock before saving.', 'satori-audit' ), 'error', $period );
        }

        if ( ! $report_id ) {
            $this->redirect_with_notice( __( 'No report found.', 'satori-audit' ), 'error' );
        }

        $meta = [
            'overview' => array_map( 'sanitize_textarea_field', $_POST['overview'] ?? [] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
        ];

        $this->reports->save_report_meta( $report_id, $meta );
        $this->reports->save_plugin_rows( $report_id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $this->reports->save_security_rows( $report_id, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $this->redirect_with_notice( __( 'Report saved.', 'satori-audit' ), 'success', $period );
    }

    /**
     * Handle CSV export.
     */
    public function handle_export_csv(): void {
        check_admin_referer( 'satori_audit_actions' );
        $report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;

        if ( ! $report_id ) {
            wp_die( esc_html__( 'Missing report.', 'satori-audit' ) );
        }

        $rows = $this->reports->get_plugin_rows( $report_id );
        $fh   = fopen( 'php://output', 'w' );

        nocache_headers();
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="satori-audit-' . $report_id . '.csv"' );

        fputcsv( $fh, [ 'Plugin Slug', 'Plugin Name', 'Description', 'Type', 'Version From', 'Version To', 'Version Current', 'Active', 'Status', 'Price Notes', 'Comments', 'Last Checked' ] );

        foreach ( $rows as $row ) {
            fputcsv(
                $fh,
                [
                    $row['plugin_slug'],
                    $row['plugin_name'],
                    $row['plugin_description'],
                    $row['plugin_type'],
                    $row['version_from'],
                    $row['version_to'],
                    $row['version_current'],
                    $row['is_active'] ? 'Yes' : 'No',
                    $row['status_flag'],
                    $row['price_notes'],
                    $row['comments'],
                    $row['last_checked'],
                ]
            );
        }

        exit;
    }

    /**
     * Handle PDF export.
     */
    public function handle_export_pdf(): void {
        check_admin_referer( 'satori_audit_actions' );
        $report_id = isset( $_POST['report_id'] ) ? (int) $_POST['report_id'] : 0;

        if ( ! $report_id ) {
            wp_die( esc_html__( 'Missing report.', 'satori-audit' ) );
        }

        $html = $this->capture_preview_html( $report_id );
        ( new Satori_Audit_Pdf() )->render_pdf( $report_id, $html );
    }

    /**
     * Render lock action.
     */
    public function handle_lock(): void {
        check_admin_referer( 'satori_audit_actions' );
        $period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : gmdate( 'Y-m' );
        $report_id = $this->reports->get_report_id_by_period( $period );

        if ( $report_id ) {
            $this->reports->lock_report( $report_id );
        }

        $this->redirect_with_notice( __( 'Report locked.', 'satori-audit' ), 'success', $period );
    }

    /**
     * Render unlock action.
     */
    public function handle_unlock(): void {
        check_admin_referer( 'satori_audit_actions' );
        $period    = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : gmdate( 'Y-m' );
        $report_id = $this->reports->get_report_id_by_period( $period );

        if ( $report_id ) {
            $this->reports->unlock_report( $report_id );
        }

        $this->redirect_with_notice( __( 'Report unlocked.', 'satori-audit' ), 'success', $period );
    }

    /**
     * Capture preview HTML for PDF export.
     */
    private function capture_preview_html( int $report_id ): string {
        $report_service = new Satori_Audit_Reports();
        $settings       = Satori_Audit_Plugin::get_settings();
        $plugin_rows    = $report_service->get_plugin_rows( $report_id );
        $security_rows  = $report_service->get_security_rows( $report_id );
        $meta           = $report_service->get_report_meta( $report_id );
        $summary        = $report_service->get_summary( $report_id );
        $period         = get_post_meta( $report_id, '_satori_audit_period', true );

        ob_start();
        include SATORI_AUDIT_PLUGIN_DIR . 'templates/admin/report-preview.php';

        return (string) ob_get_clean();
    }

    /**
     * Redirect with notice message.
     */
    private function redirect_with_notice( string $message, string $type = 'success', string $period = '' ): void {
        $args = [
            'page'                => 'satori-audit',
            'satori_audit_notice' => $message,
            'type'                => 'success' === $type ? 'updated' : 'error',
        ];

        if ( $period ) {
            $args['period'] = $period;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
