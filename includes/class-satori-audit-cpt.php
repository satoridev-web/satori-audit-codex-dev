<?php
/**
 * Custom post type registration for reports.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit\Includes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle registration of the audit report CPT.
 */
class Satori_Audit_Cpt {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'satori_audit_load_custom_post_types', [ $this, 'register_report_cpt' ] );
    }

    /**
     * Register the audit report custom post type.
     */
    public function register_report_cpt(): void {
        $labels = [
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
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'capability_type'     => 'post',
            'supports'            => [ 'title', 'editor', 'custom-fields' ],
            'rewrite'             => false,
            'map_meta_cap'        => true,
            'exclude_from_search' => true,
        ];

        register_post_type( 'satori_audit_report', $args );
    }
}
