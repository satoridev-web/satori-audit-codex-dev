<?php
/**
 * Report generation and retrieval services.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide report lifecycle helpers.
 */
class Satori_Audit_Reports {
    /**
     * Generate or refresh a report for the given period key.
     *
     * @param string|null $period Period key (YYYY-MM). Defaults to current month.
     */
    public function generate_report( ?string $period = null ): void {
        $period = $period ?: gmdate( 'Y-m' );

        do_action( 'satori_audit_before_generate_report', $period );

        // Placeholder: create or update report post and associated rows.

        do_action( 'satori_audit_after_generate_report', $period );
    }

    /**
     * Lock a report to prevent further edits.
     *
     * @param int $report_id Report post ID.
     */
    public function lock_report( int $report_id ): void {
        update_post_meta( $report_id, '_satori_audit_locked', true );
    }

    /**
     * Unlock a report for editing.
     *
     * @param int $report_id Report post ID.
     */
    public function unlock_report( int $report_id ): void {
        delete_post_meta( $report_id, '_satori_audit_locked' );
    }

    /**
     * Retrieve a report by period.
     *
     * @param string $period Period key (YYYY-MM).
     *
     * @return int|null
     */
    public function get_report_id_by_period( string $period ): ?int {
        $query = new \WP_Query(
            [
                'post_type'      => 'satori_audit_report',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_satori_audit_period',
                'meta_value'     => $period,
                'fields'         => 'ids',
            ]
        );

        return $query->have_posts() ? (int) $query->posts[0] : null;
    }
}
