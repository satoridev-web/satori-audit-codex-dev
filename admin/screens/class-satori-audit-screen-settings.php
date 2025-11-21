<?php
/**
 * Settings admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

use Satori_Audit\Includes\Satori_Audit_Plugin;
use Satori_Audit\Includes\Satori_Audit_Pdf;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Settings page for SATORI Audit.
 */
class Satori_Audit_Screen_Settings {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'satori_audit_register_settings', [ $this, 'register_settings' ] );
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings(): void {
        register_setting( 'satori_audit_settings', 'satori_audit_settings', [ $this, 'sanitize_settings' ] );

        $tabs = [
            'service'      => __( 'Service Details', 'satori-audit' ),
            'notifications'=> __( 'Notifications', 'satori-audit' ),
            'safelist'     => __( 'Safelist', 'satori-audit' ),
            'access'       => __( 'Access Control', 'satori-audit' ),
            'automation'   => __( 'Automation', 'satori-audit' ),
            'display'      => __( 'Display & Output', 'satori-audit' ),
            'pdf'          => __( 'PDF Diagnostics', 'satori-audit' ),
        ];

        foreach ( $tabs as $tab => $label ) {
            add_settings_section( 'satori_audit_' . $tab, $label, '__return_false', 'satori_audit_settings_' . $tab );
        }

        $this->add_service_fields();
        $this->add_notification_fields();
        $this->add_safelist_fields();
        $this->add_access_fields();
        $this->add_automation_fields();
        $this->add_display_fields();
        $this->add_pdf_fields();
    }

    /**
     * Sanitize settings before saving.
     *
     * @param mixed $settings Submitted settings.
     */
    public function sanitize_settings( $settings ): array {
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $defaults  = Satori_Audit_Plugin::get_default_settings();
        $sanitized = [];

        foreach ( $defaults as $key => $default ) {
            $value            = $settings[ $key ] ?? $default;
            $sanitized[ $key ] = is_numeric( $value ) ? (string) $value : sanitize_text_field( (string) $value );
        }

        $sanitized['suppress_wp_emails'] = empty( $settings['suppress_wp_emails'] ) ? 0 : 1;
        $sanitized['enforce_safelist']   = empty( $settings['enforce_safelist'] ) ? 0 : 1;
        $sanitized['enable_cron']        = empty( $settings['enable_cron'] ) ? 0 : 1;
        $sanitized['show_security']      = empty( $settings['show_security'] ) ? 0 : 1;
        $sanitized['show_known_issues']  = empty( $settings['show_known_issues'] ) ? 0 : 1;

        return $sanitized;
    }

    /**
     * Render settings page content.
     */
    public function render(): void {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'service'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tabs       = [
            'service'      => __( 'Service Details', 'satori-audit' ),
            'notifications'=> __( 'Notifications', 'satori-audit' ),
            'safelist'     => __( 'Safelist', 'satori-audit' ),
            'access'       => __( 'Access Control', 'satori-audit' ),
            'automation'   => __( 'Automation', 'satori-audit' ),
            'display'      => __( 'Display & Output', 'satori-audit' ),
            'pdf'          => __( 'PDF Diagnostics', 'satori-audit' ),
        ];

        echo '<div class="wrap satori-audit-wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Settings', 'satori-audit' ) . '</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $tab => $label ) {
            $class = $active_tab === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( [ 'page' => 'satori-audit-settings', 'tab' => $tab ], admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        echo '<form action="options.php" method="post" class="satori-audit-settings-form">';
        settings_fields( 'satori_audit_settings' );
        do_settings_sections( 'satori_audit_settings_' . $active_tab );
        submit_button( __( 'Save Settings', 'satori-audit' ) );
        echo '</form>';

        echo '</div>';
    }

    /**
     * Add fields for service tab.
     */
    private function add_service_fields(): void {
        $fields = [
            'client_name'  => __( 'Client / Organisation', 'satori-audit' ),
            'site_name'    => __( 'Site Name', 'satori-audit' ),
            'site_url'     => __( 'Site URL', 'satori-audit' ),
            'managed_by'   => __( 'Managed By', 'satori-audit' ),
            'start_date'   => __( 'Start Date', 'satori-audit' ),
            'service_date' => __( 'Default Service Date', 'satori-audit' ),
            'technician_name' => __( 'Technician Name', 'satori-audit' ),
            'technician_email' => __( 'Technician Email', 'satori-audit' ),
            'technician_phone' => __( 'Technician Phone', 'satori-audit' ),
            'logo_id'      => __( 'PDF Header Logo ID', 'satori-audit' ),
        ];

        foreach ( $fields as $key => $label ) {
            add_settings_field( $key, $label, [ $this, 'render_text_field' ], 'satori_audit_settings_service', 'satori_audit_service', [ 'key' => $key ] );
        }
    }

    /**
     * Notification tab fields.
     */
    private function add_notification_fields(): void {
        add_settings_field( 'from_email', __( 'From Email', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_notifications', 'satori_audit_notifications', [ 'key' => 'from_email' ] );
        add_settings_field( 'default_recipients', __( 'Default Recipients', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_notifications', 'satori_audit_notifications', [ 'key' => 'default_recipients' ] );
        add_settings_field( 'webhook_url', __( 'Webhook URL', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_notifications', 'satori_audit_notifications', [ 'key' => 'webhook_url' ] );
        add_settings_field( 'suppress_wp_emails', __( 'Suppress core/plugin auto-update emails', 'satori-audit' ), [ $this, 'render_checkbox_field' ], 'satori_audit_settings_notifications', 'satori_audit_notifications', [ 'key' => 'suppress_wp_emails' ] );
    }

    /**
     * Safelist tab fields.
     */
    private function add_safelist_fields(): void {
        add_settings_field( 'enforce_safelist', __( 'Enforce safelist', 'satori-audit' ), [ $this, 'render_checkbox_field' ], 'satori_audit_settings_safelist', 'satori_audit_safelist', [ 'key' => 'enforce_safelist' ] );
        add_settings_field( 'safelist', __( 'Allowed email addresses/domains', 'satori-audit' ), [ $this, 'render_textarea_field' ], 'satori_audit_settings_safelist', 'satori_audit_safelist', [ 'key' => 'safelist' ] );
    }

    /**
     * Access control tab fields.
     */
    private function add_access_fields(): void {
        add_settings_field( 'cap_manage', __( 'Capability to manage reports', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_access', 'satori_audit_access', [ 'key' => 'cap_manage' ] );
        add_settings_field( 'cap_export', __( 'Capability to export', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_access', 'satori_audit_access', [ 'key' => 'cap_export' ] );
        add_settings_field( 'cap_settings', __( 'Capability to change settings', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_access', 'satori_audit_access', [ 'key' => 'cap_settings' ] );
        add_settings_field( 'admin_email', __( 'Administrator Email', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_access', 'satori_audit_access', [ 'key' => 'admin_email' ] );
    }

    /**
     * Automation tab fields.
     */
    private function add_automation_fields(): void {
        add_settings_field( 'enable_cron', __( 'Enable Monthly PDF Email', 'satori-audit' ), [ $this, 'render_checkbox_field' ], 'satori_audit_settings_automation', 'satori_audit_automation', [ 'key' => 'enable_cron' ] );
        add_settings_field( 'cron_day', __( 'Day of month', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_automation', 'satori_audit_automation', [ 'key' => 'cron_day' ] );
        add_settings_field( 'cron_time', __( 'Send time (HH:MM, UTC)', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_automation', 'satori_audit_automation', [ 'key' => 'cron_time' ] );
        add_settings_field( 'retain_months', __( 'Retention window (months, 0 = keep all)', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_automation', 'satori_audit_automation', [ 'key' => 'retain_months' ] );
    }

    /**
     * Display tab fields.
     */
    private function add_display_fields(): void {
        add_settings_field( 'show_security', __( 'Show Security section', 'satori-audit' ), [ $this, 'render_checkbox_field' ], 'satori_audit_settings_display', 'satori_audit_display', [ 'key' => 'show_security' ] );
        add_settings_field( 'show_known_issues', __( 'Show Known Issues section', 'satori-audit' ), [ $this, 'render_checkbox_field' ], 'satori_audit_settings_display', 'satori_audit_display', [ 'key' => 'show_known_issues' ] );
        add_settings_field( 'pdf_page_size', __( 'PDF Page Size', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_display', 'satori_audit_display', [ 'key' => 'pdf_page_size' ] );
        add_settings_field( 'pdf_orientation', __( 'PDF Orientation', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_display', 'satori_audit_display', [ 'key' => 'pdf_orientation' ] );
        add_settings_field( 'footer_text', __( 'Footer Text', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_display', 'satori_audit_display', [ 'key' => 'footer_text' ] );
    }

    /**
     * PDF tab fields.
     */
    private function add_pdf_fields(): void {
        add_settings_field( 'pdf_engine', __( 'PDF Engine', 'satori-audit' ), [ $this, 'render_pdf_status' ], 'satori_audit_settings_pdf', 'satori_audit_pdf', [ 'key' => 'pdf_engine' ] );
        add_settings_field( 'pdf_path', __( 'Engine Path', 'satori-audit' ), [ $this, 'render_text_field' ], 'satori_audit_settings_pdf', 'satori_audit_pdf', [ 'key' => 'pdf_path' ] );
    }

    /**
     * Render simple text input.
     */
    public function render_text_field( array $args ): void {
        $settings = Satori_Audit_Plugin::get_settings();
        $key      = $args['key'];
        printf( '<input type="text" class="regular-text" name="satori_audit_settings[%1$s]" value="%2$s" />', esc_attr( $key ), esc_attr( $settings[ $key ] ?? '' ) );
    }

    /**
     * Render textarea.
     */
    public function render_textarea_field( array $args ): void {
        $settings = Satori_Audit_Plugin::get_settings();
        $key      = $args['key'];
        printf( '<textarea class="large-text" rows="4" name="satori_audit_settings[%1$s]">%2$s</textarea>', esc_attr( $key ), esc_textarea( $settings[ $key ] ?? '' ) );
    }

    /**
     * Render checkbox field.
     */
    public function render_checkbox_field( array $args ): void {
        $settings = Satori_Audit_Plugin::get_settings();
        $key      = $args['key'];
        printf( '<label><input type="checkbox" name="satori_audit_settings[%1$s]" value="1" %2$s /> %3$s</label>', esc_attr( $key ), checked( ! empty( $settings[ $key ] ), true, false ), esc_html__( 'Enabled', 'satori-audit' ) );
    }

    /**
     * Render PDF diagnostics field.
     */
    public function render_pdf_status(): void {
        $pdf   = new Satori_Audit_Pdf();
        $diag  = $pdf->get_diagnostics();
        $label = sprintf( '%1$s (%2$s)', $diag['engine'], $diag['status'] );
        echo '<p>' . esc_html( $label ) . '</p>';
        echo '<ul class="ul-disc">';
        foreach ( $diag['messages'] as $message ) {
            echo '<li>' . esc_html( $message ) . '</li>';
        }
        echo '</ul>';
    }
}
