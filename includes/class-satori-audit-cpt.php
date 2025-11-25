<?php
/**
 * Custom post type registration for reports.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle registration of the audit report CPT.
 */
class Cpt {
    /**
     * Register the audit report custom post type.
     *
     * @return void
     */
    public static function register(): void {
        // Avoid duplicate registration during the same request.
        if ( post_type_exists( 'satori_audit_report' ) ) {
            return;
        }

        $labels = array(
            'name'               => _x( 'Audit Reports', 'post type general name', 'satori-audit' ),
            'singular_name'      => _x( 'Audit Report', 'post type singular name', 'satori-audit' ),
            'menu_name'          => _x( 'Audit Reports', 'admin menu', 'satori-audit' ),
            'name_admin_bar'     => _x( 'Audit Report', 'add new on admin bar', 'satori-audit' ),
            'add_new'            => _x( 'Add New', 'audit report', 'satori-audit' ),
            'add_new_item'       => __( 'Add New Audit Report', 'satori-audit' ),
            'new_item'           => __( 'New Audit Report', 'satori-audit' ),
            'edit_item'          => __( 'Edit Audit Report', 'satori-audit' ),
            'view_item'          => __( 'View Audit Report', 'satori-audit' ),
            'all_items'          => __( 'All Audit Reports', 'satori-audit' ),
            'search_items'       => __( 'Search Audit Reports', 'satori-audit' ),
            'not_found'          => __( 'No audit reports found.', 'satori-audit' ),
            'not_found_in_trash' => __( 'No audit reports found in Trash.', 'satori-audit' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'capability_type'     => 'post',
            'supports'            => array( 'title' ),
            'rewrite'             => false,
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
        );

        register_post_type( 'satori_audit_report', $args );

        add_action( 'add_meta_boxes_satori_audit_report', array( self::class, 'register_editor_metabox' ) );
    }

    /**
     * Provide a link to the custom Report Editor UI.
     *
     * @return void
     */
    public static function register_editor_metabox(): void {
        $capabilities = Screen_Settings::get_capabilities();
        $manage_cap   = $capabilities['manage'];

        if ( ! current_user_can( $manage_cap ) ) {
            return;
        }

        add_meta_box(
            'satori-audit-report-editor-link',
            __( 'Report Editor (Template v2)', 'satori-audit' ),
            array( self::class, 'render_editor_metabox' ),
            'satori_audit_report',
            'side',
            'high'
        );
    }

    /**
     * Render metabox content linking to the custom editor.
     *
     * @param \WP_Post $post Current post.
     * @return void
     */
    public static function render_editor_metabox( \WP_Post $post ): void {
        $url = add_query_arg(
            array(
                'page'      => 'satori-audit-report-editor',
                'report_id' => $post->ID,
                'template'  => 'v2',
            ),
            admin_url( 'admin.php' )
        );

        echo '<p>' . esc_html__( 'Use the custom editor to manage Template v2 fields and layout.', 'satori-audit' ) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open Report Editor', 'satori-audit' ) . '</a></p>';
    }
}
