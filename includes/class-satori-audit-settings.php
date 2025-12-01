<?php
/**
 * Shared settings helper for SATORI Audit.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Centralised settings accessors.
 */
class Settings {
        /**
         * Option key for storing all plugin settings.
         *
         * @var string
         */
        const OPTION_KEY = Plugin::SETTINGS_OPTION;

        /**
         * Fetch defaults for all settings.
         *
         * @return array<string,mixed>
         */
        public static function get_default_settings(): array {
                return array(
                        // Service details.
                        'service_client'                 => '',
                        'service_site_name'              => '',
                        'service_site_url'               => '',
                        'service_managed_by'             => 'SATORI',
                        'service_start_date'             => '',
                        'service_notes'                  => '',

                        // Notifications.
                        'notify_from_email'              => get_bloginfo( 'admin_email' ),
                        'notify_recipients'              => '',
                        'notify_subject_prefix'          => '',
                        'notify_send_on_publish'         => 0,
                        'notify_send_on_update'          => 0,

                        // Safelist.
                        'safelist_emails'                => '',
                        'safelist_domains'               => '',

                        // Access control.
                        'capability_manage'              => 'manage_options',
                        'capability_view_reports'        => 'manage_options',
                        'hide_menu_from_non_admin'       => 1,

                        // Automation.
                        'automation_enabled'             => 0,
                        'automation_frequency'           => 'none',
                        'automation_day_of_month'        => '1',
                        'automation_time_of_day'         => '03:00',
                        'automation_include_attachments' => 0,

                        // Display & PDF.
                        'display_date_format'            => 'j F Y',
                        'display_show_debug_section'     => 0,
                        'pdf_logo_url'                   => '',
                        'pdf_footer_text'                => '',
                        'pdf_debug_html'                 => 0,

                        // PDF engine.
                        'pdf_engine'                     => 'automatic',
                        'pdf_paper_size'                 => 'A4',
                        'pdf_orientation'                => 'portrait',
                        'pdf_font_family'                => 'Helvetica',

                        // Diagnostics.
                        'debug_mode'                     => 0,
                        'log_to_file'                    => 0,
                        'log_retention_days'             => '90',
                        'plugin_update_source'           => 'none',
                );
        }

        /**
         * Return merged settings with defaults applied.
         *
         * @return array<string,mixed>
         */
        public static function get_settings(): array {
                $saved    = get_option( self::OPTION_KEY, array() );
                $defaults = self::get_default_settings();

                if ( ! is_array( $saved ) ) {
                        $saved = array();
                }

                return array_merge( $defaults, $saved );
        }

        /**
         * Retrieve a single setting value with fallback.
         *
         * @param string $key     Setting key.
         * @param mixed  $default Optional default override.
         * @return mixed
         */
        public static function get_setting( string $key, $default = null ) {
                $settings = self::get_settings();

                if ( array_key_exists( $key, $settings ) ) {
                        return $settings[ $key ];
                }

                return $default;
        }

        /**
         * Update a single setting.
         *
         * @param string $key   Setting key.
         * @param mixed  $value Setting value.
         * @return void
         */
        public static function update_setting( string $key, $value ): void {
                $settings         = self::get_settings();
                $settings[ $key ] = $value;

                update_option( self::OPTION_KEY, $settings );
        }
}
