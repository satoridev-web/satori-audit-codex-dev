<?php
/**
 * Settings screen controller for SATORI Audit.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------
 * Class: Screen_Settings
 * -------------------------------------------------*/
class Screen_Settings {

	/**
	 * Option key for storing all plugin settings as an array.
	 *
	 * @var string
	 */
        const OPTION_KEY = Settings::OPTION_KEY;

	/**
	 * Base settings page slug (submenu slug).
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'satori-audit-settings';

	/**
	 * Initialise hooks.
	 *
	 * @return void
	 */
        public static function init(): void {
                add_action( 'admin_init', array( self::class, 'register_settings' ) );
                add_action( 'admin_init', array( self::class, 'log_admin_init' ) );
        }

	/* -------------------------------------------------
	 * Tabs
	 * -------------------------------------------------*/

	/**
	 * Get tab definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	protected static function get_tabs(): array {
		return array(
			'service'    => array(
				'label' => __( 'Service Details', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-service',
			),
			'notify'     => array(
				'label' => __( 'Notifications', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-notifications',
			),
			'safelist'   => array(
				'label' => __( 'Recipient Safelist', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-safelist',
			),
			'access'     => array(
				'label' => __( 'Access Control', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-access',
			),
			'automation' => array(
				'label' => __( 'Automation', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-automation',
			),
			'display'    => array(
				'label' => __( 'Display & Output', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-display',
			),
			'pdfdiag'    => array(
				'label' => __( 'PDF Engine & Diagnostics', 'satori-audit' ),
				'page'  => self::PAGE_SLUG . '-pdfdiag',
			),
		);
	}

	/**
	 * Get the current tab slug.
	 *
	 * @return string
	 */
	protected static function get_current_tab(): string {
		$tabs = self::get_tabs();
		$tab  = isset( $_GET['tab'] ) ? (string) wp_unslash( $_GET['tab'] ) : 'service'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return isset( $tabs[ $tab ] ) ? $tab : 'service';
	}

	/* -------------------------------------------------
	 * Settings registration
	 * -------------------------------------------------*/

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		$tabs = self::get_tabs();

		/* -------------------------------------------------
		 * Register main option
		 * -------------------------------------------------*/
                register_setting(
                        'satori_audit_settings_group',
                        self::OPTION_KEY,
                        array(
                                'type'              => 'array',
                                'sanitize_callback' => array( self::class, 'sanitize_settings' ),
                                'default'           => self::get_default_settings(),
                        )
                );

		/* -------------------------------------------------
		 * SECTION: Service Details (tab: service)
		 * -------------------------------------------------*/
		$service_page = $tabs['service']['page'];

		add_settings_section(
            'satori_audit_section_service',
            __( 'Service Details', 'satori-audit' ),
            array( self::class, 'render_service_section_intro' ),
            $service_page
        );

		self::add_text_field(
			'service_client',
			__( 'Client', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Client or company name.', 'satori-audit' )
		);

		self::add_text_field(
			'service_site_name',
			__( 'Site Name', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Label used in reports for this site.', 'satori-audit' )
		);

		self::add_text_field(
			'service_site_url',
			__( 'Site URL', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Primary site URL for this installation.', 'satori-audit' )
		);

		self::add_text_field(
			'service_managed_by',
			__( 'Managed By', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Who manages the site (e.g. SATORI, in-house team).', 'satori-audit' )
		);

		self::add_text_field(
			'service_start_date',
			__( 'Start Date', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Service start date for log/report headers (e.g. "DECEMBER 2023").', 'satori-audit' ),
			'DECEMBER 2023'
		);

		self::add_textarea_field(
			'service_notes',
			__( 'Service Notes', 'satori-audit' ),
			'satori_audit_section_service',
			$service_page,
			__( 'Internal notes about the service arrangement.', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTION: Notifications (tab: notify)
		 * -------------------------------------------------*/
		$notify_page = $tabs['notify']['page'];

		add_settings_section(
			'satori_audit_section_notifications',
			__( 'Notifications', 'satori-audit' ),
			array( self::class, 'render_notifications_section_intro' ),
			$notify_page
		);

		self::add_text_field(
			'notify_from_email',
			__( 'From Email', 'satori-audit' ),
			'satori_audit_section_notifications',
			$notify_page,
			__( 'Email address used as the sender for reports.', 'satori-audit' )
		);

		self::add_textarea_field(
			'notify_recipients',
			__( 'Send Reports To', 'satori-audit' ),
			'satori_audit_section_notifications',
			$notify_page,
			__( 'One email per line. Reports will be sent to these recipients.', 'satori-audit' )
		);

		self::add_text_field(
			'notify_subject_prefix',
			__( 'Subject Prefix', 'satori-audit' ),
			'satori_audit_section_notifications',
			$notify_page,
			__( 'Optional prefix for email subjects (e.g. "[BALL SERVICE LOG]").', 'satori-audit' )
		);

		self::add_checkbox_field(
			'notify_send_on_publish',
			__( 'Send on Publish', 'satori-audit' ),
			'satori_audit_section_notifications',
			$notify_page,
			__( 'Automatically send notifications when a report is first published.', 'satori-audit' )
		);

		self::add_checkbox_field(
			'notify_send_on_update',
			__( 'Send on Update', 'satori-audit' ),
			'satori_audit_section_notifications',
			$notify_page,
			__( 'Automatically send notifications when an existing report is updated.', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTION: Recipient Safelist (tab: safelist)
		 * -------------------------------------------------*/
		$safelist_page = $tabs['safelist']['page'];

		add_settings_section(
			'satori_audit_section_safelist',
			__( 'Recipient Safelist', 'satori-audit' ),
			array( self::class, 'render_safelist_section_intro' ),
			$safelist_page
		);

		self::add_textarea_field(
			'safelist_emails',
			__( 'Allowed Email Addresses', 'satori-audit' ),
			'satori_audit_section_safelist',
			$safelist_page,
			__( 'Limit notifications to these email addresses (one per line). If empty, all addresses are allowed.', 'satori-audit' )
		);

		self::add_textarea_field(
			'safelist_domains',
			__( 'Allowed Domains', 'satori-audit' ),
			'satori_audit_section_safelist',
			$safelist_page,
			__( 'Optionally restrict recipients to these domains (e.g. "ballaustralia.com"). One per line.', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTION: Access Control (tab: access)
		 * -------------------------------------------------*/
		$access_page = $tabs['access']['page'];

		add_settings_section(
			'satori_audit_section_access',
			__( 'Access Control', 'satori-audit' ),
			array( self::class, 'render_access_section_intro' ),
			$access_page
		);

		self::add_text_field(
			'capability_manage',
			__( 'Manage Capability', 'satori-audit' ),
			'satori_audit_section_access',
			$access_page,
			__( 'Capability required to manage settings (default: manage_options).', 'satori-audit' )
		);

		self::add_text_field(
			'capability_view_reports',
			__( 'View Reports Capability', 'satori-audit' ),
			'satori_audit_section_access',
			$access_page,
			__( 'Capability required to view reports (default: manage_options).', 'satori-audit' )
		);

		self::add_checkbox_field(
			'hide_menu_from_non_admin',
			__( 'Hide Menu from Non-Admins', 'satori-audit' ),
			'satori_audit_section_access',
			$access_page,
			__( 'Hide SATORI Audit menus for users who lack the manage capability.', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTION: Automation (tab: automation)
		 * -------------------------------------------------*/
		$automation_page = $tabs['automation']['page'];

		add_settings_section(
			'satori_audit_section_automation',
			__( 'Automation', 'satori-audit' ),
			array( self::class, 'render_automation_section_intro' ),
			$automation_page
		);

		self::add_checkbox_field(
			'automation_enabled',
			__( 'Enable Automation', 'satori-audit' ),
			'satori_audit_section_automation',
			$automation_page,
			__( 'Allow the plugin to schedule recurring report generation.', 'satori-audit' )
		);

		self::add_select_field(
			'automation_frequency',
			__( 'Default Frequency', 'satori-audit' ),
			'satori_audit_section_automation',
			$automation_page,
			array(
				'none'    => __( 'None (manual only)', 'satori-audit' ),
				'monthly' => __( 'Monthly', 'satori-audit' ),
				'weekly'  => __( 'Weekly', 'satori-audit' ),
			),
			__( 'How often to run the default automated report job.', 'satori-audit' )
		);

		self::add_text_field(
			'automation_day_of_month',
			__( 'Day of Month', 'satori-audit' ),
			'satori_audit_section_automation',
			$automation_page,
			__( 'Day of month for monthly runs (1–28).', 'satori-audit' ),
			'1'
		);

		self::add_text_field(
			'automation_time_of_day',
			__( 'Time of Day', 'satori-audit' ),
			'satori_audit_section_automation',
			$automation_page,
			__( 'Time in 24-hour format (e.g. 03:00).', 'satori-audit' ),
			'03:00'
		);

		self::add_checkbox_field(
			'automation_include_attachments',
			__( 'Include Attachments', 'satori-audit' ),
			'satori_audit_section_automation',
			$automation_page,
			__( 'Attach PDFs/CSVs to automated emails when available.', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTION: Display & PDF Output (tab: display)
		 * -------------------------------------------------*/
		$display_page = $tabs['display']['page'];

		add_settings_section(
			'satori_audit_section_display',
			__( 'Display & PDF Output', 'satori-audit' ),
			array( self::class, 'render_display_section_intro' ),
			$display_page
		);

		self::add_text_field(
			'display_date_format',
			__( 'Date Format', 'satori-audit' ),
			'satori_audit_section_display',
			$display_page,
			__( 'PHP date() format string used in reports (e.g. "j F Y").', 'satori-audit' )
		);

		self::add_checkbox_field(
			'display_show_debug_section',
			__( 'Show Debug Section', 'satori-audit' ),
			'satori_audit_section_display',
			$display_page,
			__( 'Include a diagnostics section at the end of each report.', 'satori-audit' )
		);

		self::add_text_field(
			'pdf_logo_url',
			__( 'PDF Logo URL', 'satori-audit' ),
			'satori_audit_section_display',
			$display_page,
			__( 'Optional logo used in PDF headers.', 'satori-audit' )
		);

		self::add_textarea_field(
			'pdf_footer_text',
			__( 'PDF Footer Text', 'satori-audit' ),
			'satori_audit_section_display',
			$display_page,
			__( 'Optional footer text for PDFs (e.g. disclaimer, contact details).', 'satori-audit' )
		);

		/* -------------------------------------------------
		 * SECTIONS: PDF Engine + Diagnostics (tab: pdfdiag)
		 * -------------------------------------------------*/
		$pdfdiag_page = $tabs['pdfdiag']['page'];

		// PDF Engine.
		add_settings_section(
			'satori_audit_section_pdf',
			__( 'PDF Engine', 'satori-audit' ),
			array( self::class, 'render_pdf_section_intro' ),
			$pdfdiag_page
		);

                self::add_select_field(
                        'pdf_engine',
                        __( 'Engine', 'satori-audit' ),
                        'satori_audit_section_pdf',
                        $pdfdiag_page,
                        array(
                                'automatic' => __( 'Automatic (TCPDF → DOMPDF fallback)', 'satori-audit' ),
                                'none'   => __( 'Disabled', 'satori-audit' ),
                                'dompdf' => __( 'DOMPDF', 'satori-audit' ),
                                'tcpdf'  => __( 'TCPDF', 'satori-audit' ),
                        ),
                        __( 'Automatic (recommended): TCPDF preferred with DOMPDF fallback.', 'satori-audit' )
                );

		self::add_text_field(
			'pdf_paper_size',
			__( 'Paper Size', 'satori-audit' ),
			'satori_audit_section_pdf',
			$pdfdiag_page,
			__( 'Paper size code (e.g. A4, Letter).', 'satori-audit' ),
			'A4'
		);

                self::add_select_field(
                        'pdf_orientation',
                        __( 'Orientation', 'satori-audit' ),
                        'satori_audit_section_pdf',
                        $pdfdiag_page,
                        array(
                                'portrait'  => __( 'Portrait', 'satori-audit' ),
                                'landscape' => __( 'Landscape', 'satori-audit' ),
                        ),
                        __( 'Default PDF page orientation.', 'satori-audit' )
                );

                self::add_text_field(
                        'pdf_font_family',
                        __( 'Font Family', 'satori-audit' ),
                        'satori_audit_section_pdf',
                        $pdfdiag_page,
                        __( 'Base font family supported by the chosen engine.', 'satori-audit' ),
                        'Helvetica'
                );

                add_settings_field(
                        'pdf_debug_html',
                        __( 'PDF debug mode', 'satori-audit' ),
                        array( self::class, 'render_pdf_debug_field' ),
                        $pdfdiag_page,
                        'satori_audit_section_pdf'
                );

                // Diagnostics.
                add_settings_section(
                        'satori_audit_section_diagnostics',
                        __( 'Diagnostics', 'satori-audit' ),
			array( self::class, 'render_diagnostics_section_intro' ),
			$pdfdiag_page
		);

                self::add_checkbox_field(
                        'debug_mode',
                        __( 'Debug Mode', 'satori-audit' ),
                        'satori_audit_section_diagnostics',
                        $pdfdiag_page,
                        __( 'Enable verbose debug logging for SATORI Audit.', 'satori-audit' )
                );

                self::add_select_field(
                        'plugin_update_source',
                        __( 'Plugin Update Source (Simple History)', 'satori-audit' ),
                        'satori_audit_section_diagnostics',
                        $pdfdiag_page,
                        array(
                                'none'                => __( 'Disabled', 'satori-audit' ),
                                'simple_history_safe' => __( 'Simple History (safe)', 'satori-audit' ),
                        ),
                        __( 'Select whether to merge plugin updates from Simple History when schema checks pass.', 'satori-audit' )
                );

                self::add_checkbox_field(
                        'log_to_file',
                        __( 'Log to File', 'satori-audit' ),
                        'satori_audit_section_diagnostics',
                        $pdfdiag_page,
			__( 'Persist logs to a file in wp-content (implementation TBD).', 'satori-audit' )
		);

		self::add_text_field(
			'log_retention_days',
			__( 'Log Retention (days)', 'satori-audit' ),
			'satori_audit_section_diagnostics',
			$pdfdiag_page,
			__( 'Target number of days to retain logs (for future cleanup routines).', 'satori-audit' ),
			'90'
		);
	}

	/* -------------------------------------------------
	 * Section callbacks
	 * -------------------------------------------------*/

	public static function render_service_section_intro(): void {
		echo '<p>' . esc_html__( 'Configure service metadata used in monthly service logs and report headers.', 'satori-audit' ) . '</p>';
	}

	public static function render_notifications_section_intro(): void {
		echo '<p>' . esc_html__( 'Control who receives reports, and how notification emails are sent.', 'satori-audit' ) . '</p>';
	}

	public static function render_safelist_section_intro(): void {
		echo '<p>' . esc_html__( 'Optionally restrict outgoing notifications to a known-good set of recipients.', 'satori-audit' ) . '</p>';
	}

	public static function render_access_section_intro(): void {
		echo '<p>' . esc_html__( 'Set capabilities and visibility rules for SATORI Audit screens.', 'satori-audit' ) . '</p>';
	}

	public static function render_automation_section_intro(): void {
		echo '<p>' . esc_html__( 'High-level automation defaults. Actual scheduling is handled elsewhere.', 'satori-audit' ) . '</p>';
	}

	public static function render_display_section_intro(): void {
		echo '<p>' . esc_html__( 'Control how dates, debug sections, and branding appear in HTML/PDF output.', 'satori-audit' ) . '</p>';
	}

        public static function render_pdf_section_intro(): void {
                echo '<p>' . esc_html__( 'Configure the PDF engine and core rendering options (Automatic uses TCPDF with DOMPDF fallback).', 'satori-audit' ) . '</p>';
        }

	public static function render_diagnostics_section_intro(): void {
		echo '<p>' . esc_html__( 'Turn on debugging and tune log behaviour for troubleshooting.', 'satori-audit' ) . '</p>';
	}

	/* -------------------------------------------------
	 * Field helpers
	 * -------------------------------------------------*/

	protected static function add_text_field( string $id, string $label, string $section, string $page, string $description = '', string $placeholder = '' ): void {
		add_settings_field(
			$id,
			$label,
			array( self::class, 'render_text_field' ),
			$page,
			$section,
			array(
				'id'          => $id,
				'description' => $description,
				'placeholder' => $placeholder,
			)
		);
	}

	protected static function add_textarea_field( string $id, string $label, string $section, string $page, string $description = '' ): void {
		add_settings_field(
			$id,
			$label,
			array( self::class, 'render_textarea_field' ),
			$page,
			$section,
			array(
				'id'          => $id,
				'description' => $description,
			)
		);
	}

	protected static function add_checkbox_field( string $id, string $label, string $section, string $page, string $description = '' ): void {
		add_settings_field(
			$id,
			$label,
			array( self::class, 'render_checkbox_field' ),
			$page,
			$section,
			array(
				'id'          => $id,
				'description' => $description,
			)
		);
	}

	protected static function add_select_field( string $id, string $label, string $section, string $page, array $options, string $description = '' ): void {
		add_settings_field(
			$id,
			$label,
			array( self::class, 'render_select_field' ),
			$page,
			$section,
			array(
				'id'          => $id,
				'options'     => $options,
				'description' => $description,
			)
		);
	}

	/* -------------------------------------------------
	 * Field renderers
	 * -------------------------------------------------*/

	public static function render_text_field( array $args ): void {
                $settings    = self::get_settings();
                $id          = $args['id'] ?? '';
                $value       = isset( $settings[ $id ] ) ? (string) $settings[ $id ] : '';
                $placeholder = isset( $args['placeholder'] ) ? (string) $args['placeholder'] : '';

		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" placeholder="%4$s" />',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}

	public static function render_textarea_field( array $args ): void {
                $settings = self::get_settings();
                $id       = $args['id'] ?? '';
                $value    = isset( $settings[ $id ] ) ? (string) $settings[ $id ] : '';

		printf(
			'<textarea class="large-text" rows="4" id="%1$s" name="%2$s[%1$s]">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_textarea( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}

        public static function render_checkbox_field( array $args ): void {
                $settings = self::get_settings();
                $id       = $args['id'] ?? '';
                $checked  = ! empty( $settings[ $id ] );

                printf(
                        '<input type="hidden" name="%2$s[%1$s]" value="0" /> <label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
                        esc_attr( $id ),
                        esc_attr( self::OPTION_KEY ),
                        checked( $checked, true, false ),
                        isset( $args['description'] ) ? esc_html( (string) $args['description'] ) : ''
                );
        }

        /**
         * Render the PDF debug mode checkbox with description.
         *
         * @return void
         */
        public static function render_pdf_debug_field(): void {
                $settings       = self::get_settings();
                $pdf_debug_html = ! empty( $settings['pdf_debug_html'] );

                printf(
                        '<input type="hidden" name="%1$s[pdf_debug_html]" value="0" />'
                        . '<label><input type="checkbox" name="%1$s[pdf_debug_html]" id="satori_audit_pdf_debug_html" value="1" %2$s /> %3$s</label>'
                        . '<p class="description">%4$s</p>',
                        esc_attr( self::OPTION_KEY ),
                        checked( $pdf_debug_html, true, false ),
                        esc_html__( 'Enable PDF debug mode (output raw HTML instead of PDF for administrators).', 'satori-audit' ),
                        esc_html__( 'For development and troubleshooting only. This bypasses DOMPDF and outputs the HTML intended for PDF generation.', 'satori-audit' )
                );
        }

	public static function render_select_field( array $args ): void {
                $settings = self::get_settings();
                $id       = $args['id'] ?? '';
                $options  = $args['options'] ?? array();
                $value    = isset( $settings[ $id ] ) ? (string) $settings[ $id ] : '';

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY )
		);

		foreach ( $options as $opt_value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( (string) $opt_value ),
				selected( $value, (string) $opt_value, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select>';

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
	}

	/* -------------------------------------------------
	 * Settings helpers
	 * -------------------------------------------------*/

        /**
         * Get settings with defaults merged in.
         *
         * @return array<string,mixed>
         */
        public static function get_settings(): array {
                return Settings::get_settings();
        }

        /**
         * Get resolved capabilities from settings.
         *
         * @return array{manage:string,view:string}
         */
        public static function get_capabilities(): array {
                $settings = self::get_settings();

                $caps = array(
                        'manage' => isset( $settings['capability_manage'] ) && ! empty( $settings['capability_manage'] )
                                ? (string) $settings['capability_manage']
                                : 'manage_options',
                        'view'   => isset( $settings['capability_view_reports'] ) && ! empty( $settings['capability_view_reports'] )
                                ? (string) $settings['capability_view_reports']
                                : 'manage_options',
                );

                self::log_debug(
                        sprintf(
                                'Resolved capabilities: manage=%s, view=%s, hide_menu_from_non_admin=%d.',
                                $caps['manage'],
                                $caps['view'],
                                ! empty( $settings['hide_menu_from_non_admin'] ) ? 1 : 0
                        ),
                        $settings
                );

                return $caps;
        }

        /**
         * Determine if debug mode is enabled.
         *
         * @param array<string,mixed>|null $settings Settings array to reuse.
         * @return bool
         */
        protected static function is_debug_mode( ?array $settings = null ): bool {
                $settings = $settings ?? self::get_settings();

                return ! empty( $settings['debug_mode'] );
        }

        /**
         * Log a debug message when debug mode is enabled.
         *
         * @param string                   $message  Message to log.
         * @param array<string,mixed>|null $settings Settings array to reuse.
         * @return void
         */
        public static function log_debug( string $message, ?array $settings = null ): void {
                if ( ! self::is_debug_mode( $settings ) ) {
                        return;
                }

                if ( function_exists( 'satori_audit_log' ) ) {
                        satori_audit_log( $message );
                }
        }

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
        public static function get_default_settings(): array {
                return Settings::get_default_settings();
        }

	/**
	 * Sanitize settings before saving.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
        public static function sanitize_settings( $input ): array {
                $current = self::get_settings();
                $output  = $current;

                if ( ! is_array( $input ) ) {
                        return $output;
                }

		// Simple text fields.
		$text_fields = array(
			'service_client',
			'service_site_name',
			'service_managed_by',
			'service_start_date',
			'capability_manage',
			'capability_view_reports',
			'automation_day_of_month',
			'automation_time_of_day',
			'display_date_format',
			'pdf_paper_size',
			'pdf_font_family',
			'log_retention_days',
			'notify_subject_prefix',
		);

                foreach ( $text_fields as $key ) {
                        if ( array_key_exists( $key, $input ) ) {
                                $output[ $key ] = sanitize_text_field( (string) $input[ $key ] );
                        }
                }

                // URLs.
                if ( array_key_exists( 'service_site_url', $input ) ) {
                        $output['service_site_url'] = esc_url_raw( (string) $input['service_site_url'] );
                }

                if ( array_key_exists( 'pdf_logo_url', $input ) ) {
                        $output['pdf_logo_url'] = esc_url_raw( (string) $input['pdf_logo_url'] );
                }

                // Emails.
                if ( array_key_exists( 'notify_from_email', $input ) ) {
                        $output['notify_from_email'] = sanitize_email( (string) $input['notify_from_email'] );
                }

                if ( array_key_exists( 'notify_recipients', $input ) ) {
                        $output['notify_recipients'] = sanitize_textarea_field( (string) $input['notify_recipients'] );
                }

                // Textareas.
                if ( array_key_exists( 'service_notes', $input ) ) {
                        $output['service_notes'] = wp_kses_post( (string) $input['service_notes'] );
                }

                if ( array_key_exists( 'safelist_emails', $input ) ) {
                        $output['safelist_emails'] = sanitize_textarea_field( (string) $input['safelist_emails'] );
                }

                if ( array_key_exists( 'safelist_domains', $input ) ) {
                        $output['safelist_domains'] = sanitize_textarea_field( (string) $input['safelist_domains'] );
                }

                if ( array_key_exists( 'pdf_footer_text', $input ) ) {
                        $output['pdf_footer_text'] = wp_kses_post( (string) $input['pdf_footer_text'] );
                }

                // Selects.
                if ( array_key_exists( 'automation_frequency', $input ) ) {
                        $allowed = array( 'none', 'monthly', 'weekly' );
                        $value   = (string) $input['automation_frequency'];
                        $output['automation_frequency'] = in_array( $value, $allowed, true ) ? $value : $current['automation_frequency'];
                }

                if ( array_key_exists( 'pdf_engine', $input ) ) {
                        $allowed = array( 'automatic', 'none', 'dompdf', 'tcpdf' );
                        $value   = (string) $input['pdf_engine'];
                        $output['pdf_engine'] = in_array( $value, $allowed, true ) ? $value : $current['pdf_engine'];
                }

                if ( array_key_exists( 'pdf_orientation', $input ) ) {
                        $allowed = array( 'portrait', 'landscape' );
                        $value   = (string) $input['pdf_orientation'];
                        $output['pdf_orientation'] = in_array( $value, $allowed, true ) ? $value : $current['pdf_orientation'];
                }

                if ( array_key_exists( 'plugin_update_source', $input ) ) {
                        $allowed = array( 'none', 'simple_history_safe' );
                        $value   = (string) $input['plugin_update_source'];
                        $output['plugin_update_source'] = in_array( $value, $allowed, true ) ? $value : 'none';
                }

                // Checkboxes.
                $checkboxes = array(
                        'notify_send_on_publish',
                        'notify_send_on_update',
                        'hide_menu_from_non_admin',
                        'automation_enabled',
                        'automation_include_attachments',
                        'display_show_debug_section',
                        'pdf_debug_html',
                        'debug_mode',
                        'log_to_file',
                );

                foreach ( $checkboxes as $key ) {
                        if ( array_key_exists( $key, $input ) ) {
                                $output[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
                        }
                }

                if ( function_exists( 'satori_audit_log' ) && ! empty( $output['debug_mode'] ) ) {
                        $diagnostics_summary = sprintf(
                                'Settings saved for %s (debug_mode=%d, log_to_file=%d, retention=%s).',
                                self::OPTION_KEY,
                                (int) $output['debug_mode'],
                                (int) $output['log_to_file'],
                                (string) $output['log_retention_days']
                        );

                        satori_audit_log( $diagnostics_summary );
                }

                return $output;
        }

        /**
         * Log admin initialisation when debug mode is enabled.
         *
         * @return void
         */
        public static function log_admin_init(): void {
                if ( function_exists( 'satori_audit_log' ) ) {
                        satori_audit_log( 'Admin init: registered settings for SATORI Audit.' );
                }
        }

	/* -------------------------------------------------
	 * Page renderer
	 * -------------------------------------------------*/

	/**
	 * Render the settings page wrapper.
	 *
         * Called from Admin menu callback.
         *
         * @return void
         */
        public static function render_page(): void {
                $capabilities = self::get_capabilities();
                $manage_cap   = $capabilities['manage'];

                if ( ! current_user_can( $manage_cap ) ) {
                        self::log_debug( 'Access denied to Settings for user ID ' . get_current_user_id() . '.' );
                        wp_die( esc_html__( 'You do not have permission to access this page.', 'satori-audit' ) );
                }

		$tabs        = self::get_tabs();
		$current_tab = self::get_current_tab();
		$current     = $tabs[ $current_tab ];
		?>
		<div class="wrap satori-audit-settings-wrap">
			<h1><?php esc_html_e( 'SATORI Audit – Settings', 'satori-audit' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $tabs as $slug => $tab ) {
					$class = ( $slug === $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					$url   = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $slug,
						),
						admin_url( 'admin.php' )
					);

					printf(
						'<a href="%1$s" class="%2$s">%3$s</a>',
						esc_url( $url ),
						esc_attr( $class ),
						esc_html( $tab['label'] )
					);
				}
				?>
			</h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'satori_audit_settings_group' );
				do_settings_sections( $current['page'] );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
