<?php
/**
 * Automation scheduling and cron callback handler.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Manage scheduling of automated report generation.
 */
class Automation {
        /**
         * Cron hook identifier.
         *
         * @var string
         */
        const CRON_HOOK = 'satori_audit_automation_run';
        
        /**
         * Weekly recurrence key.
         *
         * @var string
         */
        const RECURRENCE_WEEKLY = 'satori_audit_weekly';
        
        /**
         * Monthly recurrence key.
         *
         * @var string
         */
        const RECURRENCE_MONTHLY = 'satori_audit_monthly';
        
        /**
         * Initialise hooks.
         *
         * @return void
         */
        public static function init(): void {
                add_filter( 'cron_schedules', array( self::class, 'register_schedules' ) );
                add_action( 'init', array( self::class, 'maybe_schedule' ) );
                add_action( 'update_option_' . Screen_Settings::OPTION_KEY, array( self::class, 'handle_settings_update' ), 10, 2 );
                add_action( self::CRON_HOOK, array( self::class, 'run_cron' ) );
        }
        
        /**
         * Register custom cron schedules.
         *
         * @param array<string,array<string,int|string>> $schedules Existing schedules.
         *
         * @return array<string,array<string,int|string>>
         */
        public static function register_schedules( array $schedules ): array {
                $schedules[ self::RECURRENCE_WEEKLY ] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly (SATORI Audit)', 'satori-audit' ),
                );
                
                $schedules[ self::RECURRENCE_MONTHLY ] = array(
                'interval' => DAY_IN_SECONDS * 30,
                'display'  => __( 'Once Monthly (SATORI Audit)', 'satori-audit' ),
                );
                
                return $schedules;
        }
        
        /**
         * Handle settings updates to reschedule automation.
         *
         * @param array<string,mixed> $old_value Previous settings value.
         * @param array<string,mixed> $new_value New settings value.
         *
         * @return void
         */
        public static function handle_settings_update( $old_value, $new_value ): void { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
        $settings = is_array( $new_value ) ? $new_value : array();
        
        self::log( 'Settings updated; recalculating automation schedule.', $settings, array(
        'old_settings' => is_array( $old_value ) ? $old_value : array(),
        ) );
        
        self::maybe_schedule( $settings, true );
        }
        
        /**
 * Schedule or clear automation based on settings.
 *
 * @param array<string,mixed>|null $settings Settings array. Defaults to stored settings.
 * @param bool                     $force_reschedule Whether to force clearing existing events.
 *
 * @return void
 */
        public static function maybe_schedule( ?array $settings = null, bool $force_reschedule = false ): void {
        $settings = $settings ?? Screen_Settings::get_settings();
        
        if ( ! self::automation_enabled( $settings ) ) {
                wp_clear_scheduled_hook( self::CRON_HOOK );
                self::log( 'Automation disabled; cleared scheduled hook.', $settings );
                return;
        }
        
        $timestamp  = self::compute_next_run( $settings );
        $recurrence = self::get_recurrence( $settings );
        
        if ( null === $timestamp || null === $recurrence ) {
                wp_clear_scheduled_hook( self::CRON_HOOK );
                self::log( 'Unable to compute next automation run; cleared scheduled hook.', $settings );
                return;
        }
        
        $next_existing = wp_next_scheduled( self::CRON_HOOK );
        
        if ( $next_existing && ! $force_reschedule ) {
                self::log( 'Automation already scheduled; skipping reschedule.', $settings, array(
                'next_scheduled' => self::format_timestamp( (int) $next_existing ),
                ) );
                return;
        }
        
        wp_clear_scheduled_hook( self::CRON_HOOK );
        wp_schedule_event( $timestamp, $recurrence, self::CRON_HOOK );
        
        self::log( 'Automation scheduled.', $settings, array(
        'next_run_gmt' => self::format_timestamp( $timestamp ),
        'recurrence'   => $recurrence,
        ) );
        }
        
        /**
 * Cron callback entry point.
 *
 * @return void
 */
        public static function run_cron(): void {
        $settings = Screen_Settings::get_settings();
        
        if ( ! self::automation_enabled( $settings ) ) {
                self::log( 'Automation cron skipped (disabled).', $settings );
                return;
        }
        
        self::log( 'Automation cron started.', $settings, array(
        'run_timestamp' => self::format_timestamp( time() ),
        'settings'      => $settings,
        ) );
        
        // Future implementation: generate report, dispatch notifications, and optionally attach assets.
        }
        
        /**
 * Determine whether automation is enabled.
 *
 * @param array<string,mixed> $settings Settings array.
 *
 * @return bool
 */
        private static function automation_enabled( array $settings ): bool {
        $frequency = isset( $settings['automation_frequency'] ) ? (string) $settings['automation_frequency'] : 'none';
        
        return ! empty( $settings['automation_enabled'] ) && 'none' !== $frequency;
        }
        
        /**
 * Compute the next run timestamp.
 *
 * @param array<string,mixed> $settings Settings array.
 *
 * @return int|null Timestamp in GMT or null if parsing fails.
 */
        private static function compute_next_run( array $settings ): ?int {
        list( $hour, $minute ) = self::parse_time( isset( $settings['automation_time_of_day'] ) ? (string) $settings['automation_time_of_day'] : '' );
        
        $timezone = wp_timezone();
        $now      = new \DateTime( 'now', $timezone );
        
        $frequency = isset( $settings['automation_frequency'] ) ? (string) $settings['automation_frequency'] : 'none';
        
        if ( 'monthly' === $frequency ) {
                $day  = isset( $settings['automation_day_of_month'] ) ? (int) $settings['automation_day_of_month'] : 1;
                $day  = max( 1, min( 28, $day ) );
                $next = clone $now;
                $next->setDate( (int) $now->format( 'Y' ), (int) $now->format( 'n' ), $day );
                $next->setTime( $hour, $minute );
                
                if ( $next <= $now ) {
                        $next->modify( '+1 month' );
                        $next->setDate( (int) $next->format( 'Y' ), (int) $next->format( 'n' ), $day );
                }
                
                return $next->getTimestamp();
        }
        
        if ( 'weekly' === $frequency ) {
                $next = clone $now;
                $next->setTime( $hour, $minute );
                
                if ( $next <= $now ) {
                        $next->modify( '+1 week' );
                }
                
                return $next->getTimestamp();
        }
        
        return null;
        }
        
        /**
 * Determine recurrence key based on settings.
 *
 * @param array<string,mixed> $settings Settings array.
 *
 * @return string|null
 */
        private static function get_recurrence( array $settings ): ?string {
        $frequency = isset( $settings['automation_frequency'] ) ? (string) $settings['automation_frequency'] : 'none';
        
        if ( 'monthly' === $frequency ) {
                return self::RECURRENCE_MONTHLY;
        }
        
        if ( 'weekly' === $frequency ) {
                return self::RECURRENCE_WEEKLY;
        }
        
        return null;
        }
        
        /**
 * Parse a time string (HH:MM) safely.
 *
 * @param string $raw_time Raw time string.
 *
 * @return array<int,int> Array of [hour, minute].
 */
        private static function parse_time( string $raw_time ): array {
        if ( preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $raw_time ), $matches ) ) {
                $hour   = (int) $matches[1];
                $minute = (int) $matches[2];
                
                if ( $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 ) {
                        return array( $hour, $minute );
                }
        }
        
        return array( 0, 0 );
        }
        
        /**
 * Format a timestamp for logging.
 *
 * @param int $timestamp Timestamp to format.
 *
 * @return string
 */
        private static function format_timestamp( int $timestamp ): string {
        return wp_date( 'c', $timestamp );
        }
        
        /**
 * Conditionally log when debug mode is enabled.
 *
 * @param string               $message Log message.
 * @param array<string,mixed>  $settings Settings array.
 * @param array<string,mixed>  $context Context data.
 *
 * @return void
 */
        private static function log( string $message, array $settings, array $context = array() ): void {
        if ( empty( $settings['debug_mode'] ) ) {
                return;
        }
        
        if ( function_exists( 'satori_audit_log' ) ) {
                satori_audit_log( $message, $context );
        }
        }
}
