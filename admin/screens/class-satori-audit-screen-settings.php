<?php
/**
* Settings admin screen.
*
* @package Satori_Audit
*/

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Render the Settings page for SATORI Audit.
*/
class Screen_Settings {
	/**
	 * Initialise hooks for the settings screen.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

        /**
         * Register Settings API sections and fields.
         *
         * @return void
         */
        public static function register_settings(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $active_tab = self::get_active_tab();
                $fields     = self::get_field_definitions();

                register_setting(
                        'satori_audit_settings_group',
                        'satori_audit_settings',
                        array(
                                'sanitize_callback' => array( self::class, 'sanitize_settings' ),
                        )
                );

                if ( ! isset( $fields[ $active_tab ] ) ) {
                        return;
                }

                $page        = self::get_page_slug( $active_tab );
                $section_id  = 'satori_audit_section_' . $active_tab;
                $tabs        = self::get_tabs();
                $description = self::get_section_description( $active_tab );

                add_settings_section(
                        $section_id,
                        esc_html( $tabs[ $active_tab ] ),
                        static function () use ( $description ) {
                                if ( ! empty( $description ) ) {
                                        echo '<p>' . esc_html( $description ) . '</p>';
                                }
                        },
                        $page
                );

                foreach ( $fields[ $active_tab ] as $key => $field ) {
                        add_settings_field(
                                $key,
                                esc_html( $field['label'] ),
                                array( self::class, 'render_field' ),
                                $page,
                                $section_id,
                                array_merge(
                                        $field,
                                        array(
                                                'key' => $key,
                                                'tab' => $active_tab,
                                        )
                                )
                        );
                }
        }

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input from the form submission.
	 * @return array
	 */
        public static function sanitize_settings( $input ): array {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return get_option( 'satori_audit_settings', array() );
                }

                $input = is_array( $input ) ? $input : array();

		$tab = isset( $_POST['satori_audit_settings_tab'] )
		? sanitize_key( wp_unslash( (string) $_POST['satori_audit_settings_tab'] ) )
		: self::get_default_tab();

                $fields          = self::get_field_definitions();
                $current_tab     = $fields[ $tab ] ?? array();
                $current_saved   = get_option( 'satori_audit_settings', array() );
                $current_saved   = is_array( $current_saved ) ? $current_saved : array();

                foreach ( $current_tab as $key => $field ) {
                        if ( isset( $field['type'] ) && in_array( $field['type'], array( 'button', 'note' ), true ) ) {
                                continue;
                        }

                        $value                 = $input[ $key ] ?? null;
                        $current_saved[ $key ] = self::sanitize_field_value( $value, $field );
                }

		add_settings_error(
		'satori_audit_settings',
		'settings_saved',
		esc_html__( 'Settings saved.', 'satori-audit' ),
		'success'
		);

		return $current_saved;
	}

	/**
	 * Sanitize a single field value.
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	protected static function sanitize_field_value( $value, array $field ) {
		$type = $field['type'] ?? 'text';

                switch ( $type ) {
                        case 'checkbox':
                        return empty( $value ) ? 0 : 1;
                        case 'email':
                        return sanitize_email( trim( (string) wp_unslash( $value ) ) );
                        case 'url':
                        return esc_url_raw( trim( (string) wp_unslash( $value ) ) );
                        case 'textarea':
                        return trim( sanitize_textarea_field( (string) wp_unslash( $value ) ) );
                        case 'textarea_emails':
                        $raw_value = (string) wp_unslash( $value );
                        $parts     = preg_split( '/[\n,]+/', $raw_value );
                        $emails    = array();

                        foreach ( (array) $parts as $email ) {
                                $sanitized = sanitize_email( trim( (string) $email ) );
                                if ( ! empty( $sanitized ) ) {
                                        $emails[] = $sanitized;
                                }
                        }

                        return implode( "\n", $emails );
                        case 'textarea_lines':
                        $raw_value = (string) wp_unslash( $value );
                        $parts     = preg_split( '/[\n]+/', $raw_value );
                        $lines     = array();

                        foreach ( (array) $parts as $line ) {
                                $sanitized = trim( sanitize_text_field( (string) $line ) );

                                if ( '' !== $sanitized ) {
                                        $lines[] = $sanitized;
                                }
                        }

                        return implode( "\n", $lines );
                        case 'number':
                        $number = absint( $value );
                        if ( isset( $field['min'] ) ) {
                                $number = max( (int) $field['min'], $number );
                        }
                        if ( isset( $field['max'] ) ) {
                                $number = min( (int) $field['max'], $number );
                        }
                        return $number;
                        case 'time':
                        $time = trim( (string) wp_unslash( $value ) );
                        return preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '';
                        case 'date':
                        $date = trim( (string) wp_unslash( $value ) );
                        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
                        case 'select':
                        $value   = trim( (string) wp_unslash( $value ) );
                        $options = $field['options'] ?? array();
                        return array_key_exists( $value, $options ) ? $value : ( $field['default'] ?? '' );
                        case 'media':
                        return absint( $value );
                        case 'note':
                        return $field['default'] ?? '';
                        case 'text':
                        default:
                        return trim( sanitize_text_field( (string) wp_unslash( $value ) ) );
                }
        }

	/**
	 * Display settings content.
	 *
	 * @return void
	 */
        public static function render(): void {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

		$tabs       = self::get_tabs();
		$active_tab = self::get_active_tab();
		$tab_keys   = array_keys( $tabs );

		settings_errors( 'satori_audit_settings' );

		echo '<div class="wrap satori-audit-wrap">';
                echo '<h1>' . esc_html__( 'SATORI Audit – Settings', 'satori-audit' ) . '</h1>';
                self::render_tabs( $tabs, $active_tab );

                echo '<form method="post" action="options.php">';
                settings_fields( 'satori_audit_settings_group' );
                echo '<input type="hidden" name="satori_audit_settings_tab" value="' . esc_attr( $active_tab ) . '" />';
                do_settings_sections( self::get_page_slug( $active_tab ) );
                if ( in_array( $active_tab, $tab_keys, true ) ) {
			submit_button();
		}
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render the tab navigation UI.
	 *
	 * @param array  $tabs       List of tabs.
	 * @param string $active_tab Currently active tab.
	 * @return void
	 */
	protected static function render_tabs( array $tabs, string $active_tab ): void {
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $label ) {
			$url   = add_query_arg( array( 'tab' => $tab ), menu_page_url( 'satori-audit-settings', false ) );
			$class = 'nav-tab' . ( $active_tab === $tab ? ' nav-tab-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</h2>';
	}

	/**
	 * Render a settings field based on its type.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
        public static function render_field( array $args ): void {
                $key         = $args['key'];
                $type        = $args['type'] ?? 'text';
                $description = $args['description'] ?? '';
                $default     = $args['default'] ?? '';
                $value       = Plugin::get_setting( $key, $default );

                switch ( $type ) {
                        case 'textarea':
                        case 'textarea_emails':
                        case 'textarea_lines':
                        echo '<textarea class="large-text" rows="5" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']">' . esc_textarea( (string) $value ) . '</textarea>';
                        break;
			case 'checkbox':
			echo '<label><input type="checkbox" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="1" ' . checked( 1, (int) $value, false ) . ' /> ' . esc_html( $description ) . '</label>';
			$description = '';
			break;
			case 'select':
			echo '<select id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']">';
			foreach ( $args['options'] as $option_key => $label ) {
				echo '<option value="' . esc_attr( $option_key ) . '" ' . selected( $option_key, (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			break;
			case 'number':
			$min = isset( $args['min'] ) ? ' min="' . esc_attr( (string) $args['min'] ) . '"' : '';
			$max = isset( $args['max'] ) ? ' max="' . esc_attr( (string) $args['max'] ) . '"' : '';
			echo '<input type="number" class="small-text" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '"' . $min . $max . ' />';
			break;
			case 'time':
			echo '<input type="time" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
			break;
			case 'date':
			echo '<input type="date" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
			break;
			case 'media':
			echo '<input type="number" class="small-text" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
			echo '<p class="description">' . esc_html__( 'Enter the attachment ID for the PDF header logo.', 'satori-audit' ) . '</p>';
			$description = '';
			break;
                        case 'button':
                        echo '<button type="button" class="button">' . esc_html( $args['button_label'] ) . '</button>';
                        break;
                        case 'note':
                        echo '<p class="description">' . esc_html( $description ) . '</p>';
                        $description = '';
                        break;
                        case 'email':
                        case 'url':
                        case 'text':
                        default:
                        echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="' . esc_attr( $key ) . '" name="satori_audit_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
                }

		if ( ! empty( $description ) && 'checkbox' !== $type ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
	}

	/**
	 * Get the currently active tab.
	 *
	 * @return string
	 */
	protected static function get_active_tab(): string {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : self::get_default_tab();
		$tabs = self::get_tabs();

		return array_key_exists( $tab, $tabs ) ? $tab : self::get_default_tab();
	}

	/**
	 * Get default tab slug.
	 *
	 * @return string
	 */
	protected static function get_default_tab(): string {
		return 'service';
	}

	/**
	 * Retrieve tab definitions.
	 *
	 * @return array
	 */
	protected static function get_tabs(): array {
		return array(
		'service'       => __( 'Service Details', 'satori-audit' ),
		'notifications' => __( 'Notifications', 'satori-audit' ),
		'safelist'      => __( 'Recipient Safelist', 'satori-audit' ),
		'access'        => __( 'Access Control', 'satori-audit' ),
		'automation'    => __( 'Automation', 'satori-audit' ),
		'display'       => __( 'Display & Output', 'satori-audit' ),
		'pdf'           => __( 'PDF Engine & Diagnostics', 'satori-audit' ),
		);
	}

	/**
	 * Provide page slug for Settings API sections.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	protected static function get_page_slug( string $tab ): string {
		return 'satori_audit_settings_' . $tab;
	}

	/**
	 * Get field definitions grouped by tab.
	 *
	 * @return array
	 */
        protected static function get_field_definitions(): array {
                return array(
                        'service'       => array(
                                'service_site_name'   => array(
                                        'label'       => __( 'Service Site Name', 'satori-audit' ),
                                        'type'        => 'text',
                                        'description' => __( 'Name of the site being serviced.', 'satori-audit' ),
                                ),
                                'service_site_url'    => array(
                                        'label'       => __( 'Service Site URL', 'satori-audit' ),
                                        'type'        => 'url',
                                        'description' => __( 'Primary URL for the serviced site.', 'satori-audit' ),
                                ),
                                'service_client_name' => array(
                                        'label'       => __( 'Client / Organisation', 'satori-audit' ),
                                        'type'        => 'text',
                                        'description' => __( 'Name of the client or organisation.', 'satori-audit' ),
                                ),
                                'service_managed_by'  => array(
                                        'label'       => __( 'Managed By', 'satori-audit' ),
                                        'type'        => 'text',
                                        'description' => __( 'Team or company managing the service.', 'satori-audit' ),
                                ),
                                'service_start_date'  => array(
                                        'label'       => __( 'Start Date', 'satori-audit' ),
                                        'type'        => 'date',
                                        'description' => __( 'Service start date (YYYY-MM-DD).', 'satori-audit' ),
                                ),
                                'service_pdf_logo_id' => array(
                                        'label'       => __( 'PDF Header Logo ID', 'satori-audit' ),
                                        'type'        => 'media',
                                        'description' => __( 'Media attachment ID used as the PDF header logo.', 'satori-audit' ),
                                ),
                        ),
                        'notifications' => array(
                                'notifications_from_email' => array(
                                        'label'       => __( 'From Email', 'satori-audit' ),
                                        'type'        => 'email',
                                        'description' => __( 'Sender email address for notifications.', 'satori-audit' ),
                                ),
                                'notifications_recipients' => array(
                                        'label'       => __( 'Recipients', 'satori-audit' ),
                                        'type'        => 'textarea_emails',
                                        'description' => __( 'Comma or line separated email recipients.', 'satori-audit' ),
                                ),
                                'notifications_webhook_url' => array(
                                        'label'       => __( 'Webhook URL', 'satori-audit' ),
                                        'type'        => 'url',
                                        'description' => __( 'Optional webhook for external logging.', 'satori-audit' ),
                                ),
                        ),
                        'safelist'      => array(
                                'safelist_enforce' => array(
                                        'label'       => __( 'Enforce Safelist', 'satori-audit' ),
                                        'type'        => 'checkbox',
                                        'description' => __( 'Restrict notifications to safelist entries.', 'satori-audit' ),
                                ),
                                'safelist_entries' => array(
                                        'label'       => __( 'Safelist Entries', 'satori-audit' ),
                                        'type'        => 'textarea_lines',
                                        'description' => __( 'One email address or domain per line.', 'satori-audit' ),
                                ),
                        ),
                        'access'        => array(
                                'access_capability_manage' => array(
                                        'label'       => __( 'Capability to Manage', 'satori-audit' ),
                                        'type'        => 'text',
                                        'description' => __( 'Capability required to manage SATORI Audit settings and reports.', 'satori-audit' ),
                                        'default'     => 'manage_options',
                                ),
                                'access_main_admin_email'  => array(
                                        'label'       => __( 'Main Administrator Email', 'satori-audit' ),
                                        'type'        => 'email',
                                        'description' => __( 'Primary administrator contact for critical notices.', 'satori-audit' ),
                                ),
                        ),
                        'automation'    => array(
                                'automation_monthly_email_enabled' => array(
                                        'label'       => __( 'Enable Monthly PDF Email', 'satori-audit' ),
                                        'type'        => 'checkbox',
                                        'description' => __( 'Send monthly reports automatically.', 'satori-audit' ),
                                ),
                                'automation_monthly_day'          => array(
                                        'label'       => __( 'Day of Month', 'satori-audit' ),
                                        'type'        => 'number',
                                        'description' => __( 'Day of the month to send the report (1–31).', 'satori-audit' ),
                                        'default'     => 1,
                                        'min'         => 1,
                                        'max'         => 31,
                                ),
                                'automation_monthly_time'         => array(
                                        'label'       => __( 'Send Time', 'satori-audit' ),
                                        'type'        => 'time',
                                        'description' => __( 'Time of day to send the report.', 'satori-audit' ),
                                ),
                                'automation_retention_months'     => array(
                                        'label'       => __( 'Retention (months)', 'satori-audit' ),
                                        'type'        => 'number',
                                        'description' => __( 'How many months of history to keep. 0 keeps all.', 'satori-audit' ),
                                        'default'     => 0,
                                        'min'         => 0,
                                ),
                        ),
                        'display'       => array(
                                'display_show_overview' => array(
                                        'label'       => __( 'Show Overview Section', 'satori-audit' ),
                                        'type'        => 'checkbox',
                                        'description' => __( 'Display the overview in reports.', 'satori-audit' ),
                                ),
                                'display_show_plugins'    => array(
                                        'label'       => __( 'Show Plugin Table', 'satori-audit' ),
                                        'type'        => 'checkbox',
                                        'description' => __( 'Include plugin inventory in reports.', 'satori-audit' ),
                                ),
                                'display_show_security' => array(
                                        'label'       => __( 'Show Security Section', 'satori-audit' ),
                                        'type'        => 'checkbox',
                                        'description' => __( 'Include security findings in reports.', 'satori-audit' ),
                                ),
                                'display_pdf_page_size'        => array(
                                        'label'       => __( 'PDF Page Size', 'satori-audit' ),
                                        'type'        => 'select',
                                        'options'     => array(
                                                'A4'     => __( 'A4', 'satori-audit' ),
                                                'Letter' => __( 'Letter', 'satori-audit' ),
                                        ),
                                        'default'     => 'A4',
                                ),
                                'display_pdf_orientation'      => array(
                                        'label'       => __( 'PDF Orientation', 'satori-audit' ),
                                        'type'        => 'select',
                                        'options'     => array(
                                                'portrait'  => __( 'Portrait', 'satori-audit' ),
                                                'landscape' => __( 'Landscape', 'satori-audit' ),
                                        ),
                                        'default'     => 'portrait',
                                ),
                        ),
                        'pdf'           => array(
                                'pdf_engine_notes' => array(
                                        'label'       => __( 'PDF Engine Notes', 'satori-audit' ),
                                        'type'        => 'note',
                                        'description' => __( 'PDF engine diagnostics will be added in a future release.', 'satori-audit' ),
                                ),
                                'pdf_engine_test' => array(
                                        'label'        => __( 'Test PDF Engine', 'satori-audit' ),
                                        'type'         => 'button',
                                        'button_label' => __( 'Test PDF Engine', 'satori-audit' ),
                                        'description'  => __( 'Placeholder action for upcoming diagnostics.', 'satori-audit' ),
                                ),
                        ),
                );
        }

	/**
	 * Section descriptions keyed by tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	protected static function get_section_description( string $tab ): string {
		$descriptions = array(
		'service'       => __( 'Manage client and service metadata used across reports.', 'satori-audit' ),
		'notifications' => __( 'Configure notification sender and recipients.', 'satori-audit' ),
		'safelist'      => __( 'Restrict outbound notifications to approved recipients.', 'satori-audit' ),
		'access'        => __( 'Control capabilities required to manage SATORI Audit.', 'satori-audit' ),
		'automation'    => __( 'Schedule and retention preferences.', 'satori-audit' ),
		'display'       => __( 'Choose what appears in generated reports.', 'satori-audit' ),
		'pdf'           => __( 'Diagnostic actions for the PDF engine (placeholders).', 'satori-audit' ),
		);

		return $descriptions[ $tab ] ?? '';
	}
}

if ( is_admin() ) {
	Screen_Settings::init();
}
