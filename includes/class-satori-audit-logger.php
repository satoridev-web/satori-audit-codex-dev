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
     * Write a message to the WordPress debug log.
     *
     * @param string $message Message to log.
     * @param array  $context Optional context data.
     */
    public static function log( string $message, array $context = [] ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $line = $context ? sprintf( '%s %s', $message, wp_json_encode( $context ) ) : $message;
            error_log( '[SATORI Audit] ' . $line );
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
