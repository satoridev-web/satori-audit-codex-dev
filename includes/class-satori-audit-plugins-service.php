<?php
/**
 * Plugin inventory service placeholder.
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
     * Retrieve the current plugins installed on the site.
     *
     * @return array
     */
    public function get_current_plugins(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return get_plugins();
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

            $previous_version = $previous[ $slug ]['Version'] ?? '';
            $current_version  = $data['Version'] ?? '';

            if ( $previous_version !== $current_version ) {
                $diff['updated'][ $slug ] = [
                    'from' => $previous_version,
                    'to'   => $current_version,
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
