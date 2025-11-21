<?php
/**
 * Plugin inventory service.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide helpers for reading and diffing plugin state.
 */
class Satori_Audit_Plugins_Service {
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
}
