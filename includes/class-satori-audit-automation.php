<?php
/**
 * Automation entry points.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle scheduled tasks and hooks.
 */
class Satori_Audit_Automation {
    /**
     * Cron hook name.
     */
    public const CRON_HOOK = 'satori_audit_generate_monthly_report';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( self::CRON_HOOK, [ $this, 'run_scheduled_generation' ] );
    }

    /**
     * Schedule a recurring cron event if enabled.
     */
    public function maybe_schedule(): void {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        wp_schedule_event( time(), 'daily', self::CRON_HOOK );
    }

    /**
     * Placeholder for scheduled report generation.
     */
    public function run_scheduled_generation(): void {
        // Placeholder: trigger report generation for current period.
    }
}
