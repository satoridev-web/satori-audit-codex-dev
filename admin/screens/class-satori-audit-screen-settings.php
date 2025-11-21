<?php
/**
 * Settings admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Admin\Screens;

use Satori_Audit\Includes\Satori_Audit_Plugin;

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
     * Register a placeholder settings group and sections.
     */
    public function register_settings(): void {
        register_setting( 'satori_audit_settings', 'satori_audit_settings', [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'satori_audit_service_details',
            __( 'Service Details', 'satori-audit' ),
            [ $this, 'render_service_details_intro' ],
            'satori_audit_settings'
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param mixed $settings Submitted settings.
     *
     * @return array
     */
    public function sanitize_settings( $settings ): array {
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $defaults = Satori_Audit_Plugin::get_default_settings();

        $sanitized = [];

        foreach ( $defaults as $key => $value ) {
            $sanitized[ $key ] = isset( $settings[ $key ] ) ? sanitize_text_field( (string) $settings[ $key ] ) : $value;
        }

        return $sanitized;
    }

    /**
     * Render settings page content.
     */
    public function render(): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'SATORI Audit – Settings', 'satori-audit' ) . '</h1>';
        echo '<p>' . esc_html__( 'Configure service details, notifications, access control, and automation.', 'satori-audit' ) . '</p>';
        echo '<form action="options.php" method="post">';

        settings_fields( 'satori_audit_settings' );
        do_settings_sections( 'satori_audit_settings' );

        submit_button( __( 'Save Settings', 'satori-audit' ) );

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render introduction text for Service Details section.
     */
    public function render_service_details_intro(): void {
        echo '<p>' . esc_html__( 'Service details fields will be added here in future iterations.', 'satori-audit' ) . '</p>';
    }
}
