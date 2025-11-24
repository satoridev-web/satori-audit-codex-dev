<?php
/**
 * Logging helper.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Diagnostics-aware logger.
 */
class Logger {
        /**
         * Log a message when diagnostics allow it.
         *
         * @param string $message Message to log.
         * @return void
         */
        public static function log( string $message ): void {
                if ( ! self::should_log() ) {
                        return;
                }

                $timestamp = gmdate( 'Y-m-d H:i:s' );
                $line      = sprintf( '[%s] %s', $timestamp, $message );

                if ( self::should_write_to_file() ) {
                        self::write_to_file( $line );
                }

                // Mirror to PHP error log for easy debugging when enabled.
                error_log( '[SATORI Audit] ' . $line );
        }

        /**
         * Determine if logging is enabled.
         *
         * @return bool
         */
        private static function should_log(): bool {
                $debug_mode = Plugin::get_setting( 'debug_mode', 0 );

                return (bool) $debug_mode;
        }

        /**
         * Determine if logs should be written to file.
         *
         * @return bool
         */
        private static function should_write_to_file(): bool {
                $log_to_file = Plugin::get_setting( 'log_to_file', 0 );

                return (bool) $log_to_file;
        }

        /**
         * Append a line to the audit log file.
         *
         * @param string $line Prepared log line.
         * @return void
         */
        private static function write_to_file( string $line ): void {
                $upload_dir = wp_upload_dir();

                if ( empty( $upload_dir['basedir'] ) ) {
                        return;
                }

                $logs_dir = trailingslashit( $upload_dir['basedir'] ) . 'satori-audit/logs/';

                if ( ! file_exists( $logs_dir ) ) {
                        wp_mkdir_p( $logs_dir );
                }

                $log_file = $logs_dir . 'audit.log';

                $retention_days = (int) Plugin::get_setting( 'log_retention_days', 0 );
                if ( $retention_days > 0 ) {
                        self::prune_old_logs( $logs_dir, $retention_days );
                }

                file_put_contents( $log_file, $line . "\n", FILE_APPEND );
        }

        /**
         * Remove log files older than the retention window.
         *
         * @param string $logs_dir        Directory containing log files.
         * @param int    $retention_days  Days to retain logs.
         * @return void
         */
        private static function prune_old_logs( string $logs_dir, int $retention_days ): void {
                if ( $retention_days <= 0 ) {
                        return;
                }

                $cutoff = time() - ( $retention_days * DAY_IN_SECONDS );
                $files  = glob( trailingslashit( $logs_dir ) . '*.log' );

                if ( empty( $files ) ) {
                        return;
                }

                foreach ( $files as $file ) {
                        if ( ! is_file( $file ) ) {
                                continue;
                        }

                        $modified = filemtime( $file );

                        if ( false !== $modified && $modified < $cutoff ) {
                                unlink( $file );
                        }
                }
        }
}
