<?php
/**
 * Logging helper.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Basic logger wrapper for future expansion.
 */
class Satori_Audit_Logger {
    /**
     * Write a message to the WordPress debug log and a plugin log file when available.
     *
     * @param string $message Message to log.
     * @param array  $context Optional context data.
     */
    public static function log( string $message, array $context = [] ): void {
        $line = $context ? sprintf( '%s %s', $message, wp_json_encode( $context ) ) : $message;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SATORI Audit] ' . $line );
        }

        $upload_dir = wp_upload_dir();

        if ( ! empty( $upload_dir['basedir'] ) ) {
            $dir = trailingslashit( $upload_dir['basedir'] ) . 'satori-audit-logs/';

            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }

            $file = $dir . 'satori-audit.log';
            $entry = sprintf( "%s %s\n", gmdate( 'c' ), '[SATORI Audit] ' . $line );
            file_put_contents( $file, $entry, FILE_APPEND );
        }
    }
}

if ( ! function_exists( 'satori_audit_log' ) ) {
    /**
     * Helper function for logging.
     *
     * @param string $message Message to log.
     * @param array  $context Optional context data.
     */
    function satori_audit_log( string $message, array $context = [] ): void {
        Satori_Audit_Logger::log( $message, $context );
    }
}
