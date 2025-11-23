<?php
/**
 * Plugin inventory service.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide helpers for reading and diffing plugin state.
 */
class Plugins_Service {
    /**
     * Retrieve the current plugins installed on the site, keyed by slug.
     *
     * @return array
     */
    public function get_current_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins      = get_plugins();
        $active       = (array) get_option( 'active_plugins', [] );
        $normalized   = [];
        $current_time = current_time( 'mysql', true );

        foreach ( $plugins as $file => $data ) {
            $slug = $this->normalise_slug( $file );

            $normalized[ $slug ] = [
                'plugin_slug'        => $slug,
                'plugin_name'        => $data['Name'] ?? $slug,
                'plugin_description' => $data['Description'] ?? '',
                'version_current'    => $data['Version'] ?? '',
                'is_active'          => in_array( $file, $active, true ) ? 1 : 0,
                'last_checked'       => $current_time,
            ];
        }

        return $normalized;
    }

    /**
     * Normalise a plugin file path into a slug.
     *
     * @param string $plugin_file Plugin file path.
     */
    private function normalise_slug( string $plugin_file ): string {
        $parts = explode( '/', $plugin_file );

        return sanitize_title( $parts[0] ?? $plugin_file );
    }

    /**
     * Diff two plugin lists and return change flags.
     *
     * @param array $current Current plugins keyed by slug.
     * @param array $previous Previous plugins keyed by slug.
     *
     * @return array
     */
    public function diff_plugins( array $current, array $previous ): array {
        $diff = [
            'new'       => [],
            'updated'   => [],
            'deleted'   => [],
            'unchanged' => [],
        ];

        foreach ( $current as $slug => $data ) {
            if ( ! isset( $previous[ $slug ] ) ) {
                $diff['new'][ $slug ] = $data;
                continue;
            }

            $previous_version = $previous[ $slug ]['version_current'] ?? '';
            $current_version  = $data['version_current'] ?? '';

            if ( $previous_version !== $current_version ) {
                $diff['updated'][ $slug ] = [
                    'from' => $previous_version,
                    'to'   => $current_version,
                    'data' => $data,
                ];
            } else {
                $diff['unchanged'][ $slug ] = $data;
            }
        }

        foreach ( array_diff_key( $previous, $current ) as $slug => $data ) {
            $diff['deleted'][ $slug ] = $data;
        }

        return $diff;
    }

    /**
     * Refresh plugin inventory rows for a report.
     *
     * @param int $report_id Report post ID.
     * @return void
     */
    public static function refresh_plugins_for_report( int $report_id ): void {
        global $wpdb;

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $table = Tables::table( 'plugins' );

        if ( empty( $table ) ) {
            return;
        }

        $plugins = get_plugins();
        $active  = (array) get_option( 'active_plugins', array() );

        $wpdb->delete( $table, array( 'report_id' => $report_id ), array( '%d' ) );

        foreach ( $plugins as $file => $data ) {
            $slug         = sanitize_title( wp_basename( $file ) );
            $description  = isset( $data['Description'] ) ? wp_strip_all_tags( (string) $data['Description'] ) : '';
            $trimmed_desc = wp_trim_words( $description, 40, '…' );
            $is_active    = in_array( $file, $active, true ) ? 1 : 0;

            $wpdb->insert(
                $table,
                array(
                    'report_id'          => $report_id,
                    'plugin_slug'        => $slug,
                    'plugin_name'        => $data['Name'] ?? $slug,
                    'plugin_description' => $trimmed_desc,
                    'plugin_type'        => '',
                    'version_from'       => '',
                    'version_to'         => '',
                    'version_current'    => $data['Version'] ?? '',
                    'is_active'          => $is_active,
                    'status_flag'        => 'unchanged',
                    'price_notes'        => '',
                    'comments'           => '',
                    'last_checked'       => current_time( 'mysql', true ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
            );
        }
    }
}
