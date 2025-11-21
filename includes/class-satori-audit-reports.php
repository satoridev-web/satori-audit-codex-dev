<?php
/**
 * Report generation and retrieval services.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide report lifecycle helpers.
 */
class Satori_Audit_Reports {
    /**
     * Constructor to wire hooks.
     */
    public function __construct() {
        add_action( 'satori_audit_before_report_generate', [ $this, 'ensure_tables_registered' ] );
    }

    /**
     * Generate or refresh a report for the given period key.
     *
     * @param string|null $period Period key (YYYY-MM). Defaults to current month.
     * @param bool        $force  Force generation when locked.
     */
    public function generate_report( ?string $period = null, bool $force = false ): int {
        $period = $period ?: gmdate( 'Y-m' );

        do_action( 'satori_audit_before_report_generate', $period );

        $report_id = $this->get_report_id_by_period( $period );

        if ( $report_id && $this->is_locked( $report_id ) && ! $force ) {
            return $report_id;
        }

        if ( ! $report_id ) {
            $report_id = wp_insert_post(
                [
                    'post_type'   => 'satori_audit_report',
                    'post_status' => 'publish',
                    'post_title'  => sprintf( __( 'Audit Report %s', 'satori-audit' ), $period ),
                ]
            );
        }

        $this->store_report_period( $report_id, $period );
        $this->sync_plugin_rows( $report_id, $period );
        $this->initialise_meta_defaults( $report_id );
        $this->update_summary_meta( $report_id );

        do_action( 'satori_audit_after_report_generate', $report_id, $period );

        return (int) $report_id;
    }

    /**
     * Lock a report to prevent further edits.
     *
     * @param int $report_id Report post ID.
     */
    public function lock_report( int $report_id ): void {
        update_post_meta( $report_id, '_satori_audit_locked', 1 );
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
     * Determine if a report is locked.
     *
     * @param int $report_id Report ID.
     */
    public function is_locked( int $report_id ): bool {
        return (bool) get_post_meta( $report_id, '_satori_audit_locked', true );
    }

    /**
     * Retrieve a report by period.
     *
     * @param string $period Period key (YYYY-MM).
     *
     * @return int|null
     */
    public function get_report_id_by_period( string $period ): ?int {
        $query = new WP_Query(
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

    /**
     * Retrieve the most recent report before a given period.
     */
    public function get_previous_report_id( string $period ): ?int {
        $query = new WP_Query(
            [
                'post_type'      => 'satori_audit_report',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_query'     => [
                    [
                        'key'     => '_satori_audit_period',
                        'value'   => $period,
                        'compare' => '<',
                    ],
                ],
                'orderby'        => 'meta_value',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ]
        );

        return $query->have_posts() ? (int) $query->posts[0] : null;
    }

    /**
     * Persist plugin rows based on current inventory.
     */
    private function sync_plugin_rows( int $report_id, string $period ): void {
        $service          = new Satori_Audit_Plugins_Service();
        $current_plugins  = $service->get_current_plugins();
        $previous_report  = $this->get_previous_report_id( $period );
        $previous_plugins = $previous_report ? $this->get_plugin_rows_map( $previous_report ) : [];
        $existing         = $this->get_plugin_rows_map( $report_id );
        $diff             = $service->diff_plugins( $current_plugins, $previous_plugins );

        $rows = [];

        foreach ( $diff['new'] as $slug => $data ) {
            $rows[ $slug ] = $this->build_row( $data, 'new', $existing[ $slug ] ?? [] );
        }

        foreach ( $diff['updated'] as $slug => $data ) {
            $rows[ $slug ] = $this->build_row(
                array_merge( $data['data'], [
                    'version_from' => $data['from'],
                    'version_to'   => $data['to'],
                ] ),
                'updated',
                $existing[ $slug ] ?? []
            );
        }

        foreach ( $diff['unchanged'] as $slug => $data ) {
            $rows[ $slug ] = $this->build_row( $data, 'unchanged', $existing[ $slug ] ?? [] );
        }

        foreach ( $diff['deleted'] as $slug => $data ) {
            $rows[ $slug ] = $this->build_row( $data, 'deleted', $existing[ $slug ] ?? [] );
            $rows[ $slug ]['version_from'] = $data['version_current'] ?? '';
        }

        $rows = apply_filters( 'satori_audit_plugin_rows', $rows, $report_id );

        $this->replace_plugin_rows( $report_id, $rows );
    }

    /**
     * Build a plugin row payload merging manual fields.
     */
    private function build_row( array $data, string $status_flag, array $existing ): array {
        $manual_fields = [ 'plugin_type', 'price_notes', 'comments' ];
        $row           = [
            'plugin_slug'        => $data['plugin_slug'] ?? '',
            'plugin_name'        => $data['plugin_name'] ?? '',
            'plugin_description' => $data['plugin_description'] ?? '',
            'plugin_type'        => $existing['plugin_type'] ?? '',
            'version_from'       => $data['version_from'] ?? '',
            'version_to'         => $data['version_to'] ?? '',
            'version_current'    => $data['version_current'] ?? '',
            'is_active'          => (int) ( $data['is_active'] ?? 0 ),
            'status_flag'        => $status_flag,
            'price_notes'        => $existing['price_notes'] ?? '',
            'comments'           => $existing['comments'] ?? '',
            'last_checked'       => $data['last_checked'] ?? current_time( 'mysql', true ),
        ];

        foreach ( $manual_fields as $field ) {
            if ( isset( $existing[ $field ] ) && '' !== $existing[ $field ] ) {
                $row[ $field ] = $existing[ $field ];
            }
        }

        return $row;
    }

    /**
     * Insert plugin rows for a report, replacing existing.
     */
    private function replace_plugin_rows( int $report_id, array $rows ): void {
        global $wpdb;

        $table = Satori_Audit_Tables::table( 'plugins' );
        $wpdb->delete( $table, [ 'report_id' => $report_id ], [ '%d' ] );

        foreach ( $rows as $row ) {
            $wpdb->insert(
                $table,
                array_merge( $row, [ 'report_id' => $report_id ] ),
                [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d',
                ]
            );
        }
    }

    /**
     * Retrieve plugin rows keyed by slug.
     */
    public function get_plugin_rows_map( int $report_id ): array {
        $rows = $this->get_plugin_rows( $report_id );

        $mapped = [];
        foreach ( $rows as $row ) {
            $mapped[ $row['plugin_slug'] ] = $row;
        }

        return $mapped;
    }

    /**
     * Retrieve plugin rows for a report.
     */
    public function get_plugin_rows( int $report_id ): array {
        global $wpdb;

        $table = Satori_Audit_Tables::table( 'plugins' );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id = %d ORDER BY plugin_name ASC", $report_id ), ARRAY_A );
    }

    /**
     * Save plugin rows from a submitted form payload.
     */
    public function save_plugin_rows( int $report_id, array $payload ): void {
        $rows = [];

        $count = count( $payload['plugin_slug'] ?? [] );

        for ( $i = 0; $i < $count; $i++ ) {
            $slug = sanitize_title( $payload['plugin_slug'][ $i ] ?? '' );

            if ( ! $slug ) {
                continue;
            }

            $rows[ $slug ] = [
                'plugin_slug'        => $slug,
                'plugin_name'        => sanitize_text_field( $payload['plugin_name'][ $i ] ?? '' ),
                'plugin_description' => sanitize_textarea_field( $payload['plugin_description'][ $i ] ?? '' ),
                'plugin_type'        => sanitize_text_field( $payload['plugin_type'][ $i ] ?? '' ),
                'version_from'       => sanitize_text_field( $payload['version_from'][ $i ] ?? '' ),
                'version_to'         => sanitize_text_field( $payload['version_to'][ $i ] ?? '' ),
                'version_current'    => sanitize_text_field( $payload['version_current'][ $i ] ?? '' ),
                'is_active'          => isset( $payload['is_active'][ $i ] ) ? 1 : 0,
                'status_flag'        => sanitize_text_field( $payload['status_flag'][ $i ] ?? 'unchanged' ),
                'price_notes'        => sanitize_textarea_field( $payload['price_notes'][ $i ] ?? '' ),
                'comments'           => sanitize_textarea_field( $payload['comments'][ $i ] ?? '' ),
                'last_checked'       => sanitize_text_field( $payload['last_checked'][ $i ] ?? current_time( 'mysql', true ) ),
            ];
        }

        $this->replace_plugin_rows( $report_id, $rows );
        $this->update_summary_meta( $report_id );
    }

    /**
     * Save security rows from a submitted form payload.
     */
    public function save_security_rows( int $report_id, array $payload ): void {
        global $wpdb;

        $table = Satori_Audit_Tables::table( 'security' );
        $wpdb->delete( $table, [ 'report_id' => $report_id ], [ '%d' ] );

        $count = count( $payload['vulnerability_type'] ?? [] );

        for ( $i = 0; $i < $count; $i++ ) {
            $type = sanitize_text_field( $payload['vulnerability_type'][ $i ] ?? '' );
            $desc = sanitize_textarea_field( $payload['description'][ $i ] ?? '' );

            if ( '' === $type && '' === $desc ) {
                continue;
            }

            $wpdb->insert(
                $table,
                [
                    'report_id'          => $report_id,
                    'vulnerability_type' => $type,
                    'description'        => $desc,
                    'cvss_score'         => sanitize_text_field( $payload['cvss_score'][ $i ] ?? '' ),
                    'severity'           => sanitize_text_field( $payload['severity'][ $i ] ?? '' ),
                    'attack_report'      => sanitize_textarea_field( $payload['attack_report'][ $i ] ?? '' ),
                    'solution'           => sanitize_textarea_field( $payload['solution'][ $i ] ?? '' ),
                    'comments'           => sanitize_textarea_field( $payload['comments'][ $i ] ?? '' ),
                    'action_required'    => isset( $payload['action_required'][ $i ] ) ? 1 : 0,
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
            );
        }
    }

    /**
     * Fetch security rows for a report.
     */
    public function get_security_rows( int $report_id ): array {
        global $wpdb;

        $table = Satori_Audit_Tables::table( 'security' );

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id = %d ORDER BY id ASC", $report_id ), ARRAY_A );

        return apply_filters( 'satori_audit_security_rows', $rows, $report_id );
    }

    /**
     * Store the period meta.
     */
    private function store_report_period( int $report_id, string $period ): void {
        update_post_meta( $report_id, '_satori_audit_period', $period );
    }

    /**
     * Ensure default meta exists for a report.
     */
    private function initialise_meta_defaults( int $report_id ): void {
        $meta = get_post_meta( $report_id, '_satori_audit_meta', true );

        if ( ! is_array( $meta ) ) {
            $meta = [];
        }

        $defaults = [
            'service_details' => [
                'service_date'  => '',
                'start_date'    => '',
                'technician'    => '',
                'technician_id' => '',
            ],
            'overview'        => [
                'security' => '',
                'general'  => '',
                'misc'     => '',
                'comments' => '',
            ],
            'legend'          => __( 'NEW = newly installed, UPDATED = version change, DELETED = removed since last audit.', 'satori-audit' ),
        ];

        update_post_meta( $report_id, '_satori_audit_meta', wp_parse_args( $meta, $defaults ) );
    }

    /**
     * Retrieve report meta.
     */
    public function get_report_meta( int $report_id ): array {
        $meta = get_post_meta( $report_id, '_satori_audit_meta', true );

        if ( ! is_array( $meta ) ) {
            $meta = [];
        }

        return $meta;
    }

    /**
     * Save report meta.
     */
    public function save_report_meta( int $report_id, array $meta ): void {
        update_post_meta( $report_id, '_satori_audit_meta', $meta );
    }

    /**
     * Calculate and store summary statistics.
     */
    private function update_summary_meta( int $report_id ): void {
        $rows = $this->get_plugin_rows( $report_id );
        $meta = [
            'new'       => 0,
            'updated'   => 0,
            'deleted'   => 0,
            'unchanged' => 0,
        ];

        foreach ( $rows as $row ) {
            $flag = $row['status_flag'] ?? 'unchanged';

            if ( ! isset( $meta[ $flag ] ) ) {
                $flag = 'unchanged';
            }

            $meta[ $flag ]++;
        }

        update_post_meta( $report_id, '_satori_audit_summary', $meta );
    }

    /**
     * Obtain summary counts for display.
     */
    public function get_summary( int $report_id ): array {
        $stored = get_post_meta( $report_id, '_satori_audit_summary', true );

        if ( ! is_array( $stored ) ) {
            $stored = [ 'new' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0 ];
        }

        return $stored;
    }

    /**
     * Ensure table names are registered prior to generation.
     */
    public function ensure_tables_registered(): void {
        ( new Satori_Audit_Tables() )->register_table_names();
    }
}
