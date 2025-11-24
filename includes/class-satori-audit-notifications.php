<?php
/**
 * Notification dispatcher for reports.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Handle email notifications for report events.
 */
class Notifications {
        /**
         * Register hooks for notification dispatch.
         *
         * @return void
         */
        public static function init(): void {
                add_action( 'transition_post_status', array( self::class, 'maybe_send_on_status_change' ), 10, 3 );
        }

        /**
         * Send notifications for the given report and context.
         *
         * @param int    $report_id Report post ID.
         * @param string $context   Context string ('publish' or 'update').
         *
         * @return void
         */
        public static function send( int $report_id, string $context ): void {
                if ( ! in_array( $context, array( 'publish', 'update' ), true ) ) {
                        return;
                }

                $settings = self::get_settings();

                self::log( 'Notification send invoked.', $settings, array( 'context' => $context, 'report_id' => $report_id ) );

                if ( ! self::should_send_for_context( $context, $settings ) ) {
                        self::log( 'Notification skipped (context disabled).', $settings, array( 'context' => $context, 'report_id' => $report_id ) );
                        return;
                }

                $recipients = self::get_recipients( $settings );
                self::log( 'Recipients before safelist.', $settings, array( 'recipients' => $recipients, 'report_id' => $report_id, 'context' => $context ) );

                $recipients = self::apply_safelist( $recipients, $settings );
                self::log( 'Recipients after safelist.', $settings, array( 'recipients' => $recipients, 'report_id' => $report_id, 'context' => $context ) );

                if ( empty( $recipients ) ) {
                        self::log( 'Notification skipped (no recipients after safelist).', $settings, array( 'report_id' => $report_id, 'context' => $context ) );
                        return;
                }

                $subject = self::build_subject( $report_id, $context, $settings );
                $message = self::build_message_body( $report_id, $context );
                $headers = self::build_headers( $settings );

                wp_mail( $recipients, $subject, $message, $headers );
                self::log( 'Notification sent.', $settings, array( 'report_id' => $report_id, 'context' => $context, 'recipients' => $recipients ) );
        }

        /**
         * Hook callback to determine when to send notifications.
         *
         * @param string  $new_status New post status.
         * @param string  $old_status Previous post status.
         * @param WP_Post $post       Post object.
         *
         * @return void
         */
        public static function maybe_send_on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
                if ( 'satori_audit_report' !== $post->post_type ) {
                        return;
                }

                if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
                        return;
                }

                if ( 'publish' === $new_status && 'publish' !== $old_status ) {
                        self::send( (int) $post->ID, 'publish' );
                        return;
                }

                if ( 'publish' === $new_status && 'publish' === $old_status ) {
                        self::send( (int) $post->ID, 'update' );
                }
        }

        /**
         * Retrieve merged settings from the Settings screen.
         *
         * @return array<string,mixed>
         */
        private static function get_settings(): array {
                return Screen_Settings::get_settings();
        }

        /**
         * Determine if notifications should be sent for the given context.
         *
         * @param string               $context   Context string.
         * @param array<string,mixed>  $settings  Settings array.
         *
         * @return bool
         */
        private static function should_send_for_context( string $context, array $settings ): bool {
                if ( 'publish' === $context ) {
                        return ! empty( $settings['notify_send_on_publish'] );
                }

                if ( 'update' === $context ) {
                        return ! empty( $settings['notify_send_on_update'] );
                }

                return false;
        }

        /**
         * Parse recipients from settings.
         *
         * @param array<string,mixed> $settings Settings array.
         *
         * @return array<int,string>
         */
        private static function get_recipients( array $settings ): array {
                if ( empty( $settings['notify_recipients'] ) ) {
                        return array();
                }

                $raw       = (string) $settings['notify_recipients'];
                $parts     = preg_split( '/[\r\n,]+/', $raw ) ?: array();
                $emails    = array();

                foreach ( $parts as $part ) {
                        $email = trim( $part );

                        if ( '' === $email ) {
                                continue;
                        }

                        if ( is_email( $email ) ) {
                                $emails[] = $email;
                        }
                }

                return array_values( array_unique( $emails ) );
        }

        /**
         * Apply safelist filtering to the recipients.
         *
         * @param array<int,string>    $emails    Recipients.
         * @param array<string,mixed>  $settings  Settings array.
         *
         * @return array<int,string>
         */
        private static function apply_safelist( array $emails, array $settings ): array {
                $safelist_emails  = self::string_list_to_array( $settings['safelist_emails'] ?? '' );
                $safelist_domains = self::string_list_to_array( $settings['safelist_domains'] ?? '' );

                if ( empty( $safelist_emails ) && empty( $safelist_domains ) ) {
                        return $emails;
                }

                $allowed    = array();
                $dropped    = array();
                $email_map  = array_map( 'strtolower', $safelist_emails );
                $domain_map = array_map( 'strtolower', $safelist_domains );

                foreach ( $emails as $email ) {
                        $normalized = strtolower( $email );
                        $domain     = substr( $normalized, (int) strpos( $normalized, '@' ) + 1 );

                        $is_allowed = in_array( $normalized, $email_map, true ) || in_array( $domain, $domain_map, true );

                        if ( $is_allowed ) {
                                $allowed[] = $email;
                        } else {
                                $dropped[] = $email;
                        }
                }

                if ( ! empty( $dropped ) ) {
                        self::log( 'Recipients dropped by safelist.', $settings, array( 'dropped' => $dropped ) );
                }

                return $allowed;
        }

        /**
         * Build the subject line for the notification.
         *
         * @param int                  $report_id Report post ID.
         * @param string               $context   Context string.
         * @param array<string,mixed>  $settings  Settings array.
         *
         * @return string
         */
        private static function build_subject( int $report_id, string $context, array $settings ): string {
                $prefix   = trim( (string) ( $settings['notify_subject_prefix'] ?? '' ) );
                $title    = get_the_title( $report_id );
                $site     = ! empty( $settings['service_site_name'] ) ? (string) $settings['service_site_name'] : get_bloginfo( 'name' );
                $subject  = sprintf( '%s — %s', $title, $site );

                if ( '' !== $prefix ) {
                        $subject = sprintf( '[%s] %s', $prefix, $subject );
                }

                return $subject;
        }

        /**
         * Build the message body for the notification.
         *
         * @param int    $report_id Report post ID.
         * @param string $context   Context string.
         *
         * @return string
         */
        private static function build_message_body( int $report_id, string $context ): string {
                $report_title = get_the_title( $report_id );
                $action       = ( 'publish' === $context ) ? __( 'published', 'satori-audit' ) : __( 'updated', 'satori-audit' );
                $link         = admin_url( 'admin.php?page=satori-audit' );

                return sprintf(
                        /* translators: 1: report title, 2: action, 3: admin link */
                        __( 'The report "%1$s" was %2$s. View it in the SATORI Audit dashboard: %3$s', 'satori-audit' ),
                        $report_title,
                        $action,
                        $link
                );
        }

        /**
         * Build email headers for wp_mail.
         *
         * @param array<string,mixed> $settings Settings array.
         *
         * @return array<int,string>
         */
        private static function build_headers( array $settings ): array {
                $from_email = (string) ( $settings['notify_from_email'] ?? '' );

                if ( ! is_email( $from_email ) ) {
                        $from_email = get_bloginfo( 'admin_email' );
                }

                return array( 'From: ' . $from_email );
        }

        /**
         * Convert textarea or comma-separated list into array.
         *
         * @param string $value Raw list string.
         *
         * @return array<int,string>
         */
        private static function string_list_to_array( string $value ): array {
                $parts = preg_split( '/[\r\n,]+/', $value ) ?: array();
                $parts = array_map( 'trim', $parts );
                $parts = array_filter( $parts, static function ( $part ) {
                        return '' !== $part;
                } );

                return array_values( $parts );
        }

        /**
         * Conditionally log debug information.
         *
         * @param string               $message   Log message.
         * @param array<string,mixed>  $settings  Settings array.
         * @param array<string,mixed>  $context   Context data.
         *
         * @return void
         */
        private static function log( string $message, array $settings, array $context = array() ): void {
                if ( empty( $settings['debug_mode'] ) ) {
                        return;
                }

                if ( function_exists( 'satori_audit_log' ) ) {
                        $context_suffix = $context ? ' ' . wp_json_encode( $context ) : '';
                        satori_audit_log( $message . $context_suffix );
                }
        }
}
