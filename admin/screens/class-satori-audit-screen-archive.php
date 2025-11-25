<?php
/**
 * Archive admin screen.
 *
 * @package Satori_Audit
 */

declare( strict_types=1 );

namespace Satori_Audit;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the Archive page for SATORI Audit.
 */
class Screen_Archive {
        /**
         * Wire hooks for archive actions.
         *
         * @return void
         */
        public static function init(): void {
                add_action( 'admin_post_satori_audit_delete_report', array( self::class, 'handle_delete_request' ) );
        }

        /**
         * Handle delete requests from Archive actions.
         *
         * @return void
         */
        public static function handle_delete_request(): void {
                $capabilities = Screen_Settings::get_capabilities();
                $manage_cap   = $capabilities['manage'];

                if ( ! current_user_can( $manage_cap ) ) {
                        Screen_Settings::log_debug( 'Delete denied for Archive request by user ID ' . get_current_user_id() . '.' );
                        wp_die( esc_html__( 'You do not have permission to delete SATORI Audit reports.', 'satori-audit' ) );
                }

                $nonce_name  = '_satori_audit_nonce';
                $nonce_value = isset( $_REQUEST[ $nonce_name ] ) ? wp_unslash( $_REQUEST[ $nonce_name ] ) : '';

                if ( ! $nonce_value || ! wp_verify_nonce( $nonce_value, 'satori_audit_delete_report' ) ) {
                        Screen_Settings::log_debug( 'Delete request failed nonce verification.' );
                        wp_die( esc_html__( 'Invalid request. Please try again.', 'satori-audit' ) );
                }

                $report_ids = array();

                if ( isset( $_REQUEST['report_id'] ) ) {
                        $report_ids[] = absint( wp_unslash( $_REQUEST['report_id'] ) );
                }

                if ( isset( $_REQUEST['report_ids'] ) && is_array( $_REQUEST['report_ids'] ) ) {
                        $report_ids = array_merge(
                                $report_ids,
                                array_map( 'absint', (array) wp_unslash( $_REQUEST['report_ids'] ) )
                        );
                }

                $report_ids = array_filter( array_unique( $report_ids ) );

                $redirect = admin_url( 'admin.php?page=satori-audit-archive' );

                if ( empty( $report_ids ) ) {
                        $redirect = add_query_arg( 'satori_audit_notice', 'report_delete_error', $redirect );
                        wp_safe_redirect( $redirect );
                        exit;
                }

                $bulk_action      = isset( $_REQUEST['bulk_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['bulk_action'] ) ) : '';
                $is_bulk_request  = isset( $_REQUEST['report_ids'] ) && is_array( $_REQUEST['report_ids'] );

                if ( $is_bulk_request && 'delete' !== $bulk_action ) {
                        $redirect = add_query_arg( 'satori_audit_notice', 'report_delete_error', $redirect );
                        wp_safe_redirect( $redirect );
                        exit;
                }

                $success_count = 0;
                $failed_count  = 0;

                Screen_Settings::log_debug(
                        sprintf(
                                'Delete request received for report IDs: %s by user ID %d.',
                                implode( ',', $report_ids ),
                                get_current_user_id()
                        )
                );

                foreach ( $report_ids as $report_id ) {
                        $report = get_post( $report_id );

                        if ( ! $report instanceof \WP_Post || 'satori_audit_report' !== $report->post_type ) {
                                $failed_count++;
                                continue;
                        }

                        $result = wp_trash_post( $report_id );

                        if ( $result instanceof \WP_Post ) {
                                $success_count++;
                                Screen_Settings::log_debug( 'Report ID ' . $report_id . ' moved to trash.' );
                        } else {
                                $failed_count++;
                        }
                }

                $notice_key = $success_count > 0 ? 'report_deleted' : 'report_delete_error';

                $redirect = add_query_arg(
                        array(
                                'satori_audit_notice' => $notice_key,
                                'trashed'             => $success_count,
                                'failed'              => $failed_count,
                        ),
                        $redirect
                );

                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * Display archive content.
         *
         * @return void
         */
        public static function render(): void {
                $capabilities = Screen_Settings::get_capabilities();
                $view_cap     = $capabilities['view'];
                $manage_cap   = $capabilities['manage'];

                if ( ! current_user_can( $view_cap ) ) {
                        Screen_Settings::log_debug( 'Access denied to Archive for user ID ' . get_current_user_id() . '.' );
                        wp_die( esc_html__( 'You do not have permission to access this page.', 'satori-audit' ) );
                }

                $notice_key = isset( $_GET['satori_audit_notice'] ) ? sanitize_key( wp_unslash( $_GET['satori_audit_notice'] ) ) : '';
                $trashed    = isset( $_GET['trashed'] ) ? absint( $_GET['trashed'] ) : 0;
                $failed     = isset( $_GET['failed'] ) ? absint( $_GET['failed'] ) : 0;

                $selected_report_id = isset( $_GET['report_id'] ) ? absint( $_GET['report_id'] ) : 0;
                $report            = $selected_report_id ? get_post( $selected_report_id ) : null;
                $period            = $selected_report_id ? get_post_meta( $selected_report_id, '_satori_audit_period', true ) : '';

                echo '<div class="wrap satori-audit-wrap">';
                echo '<h1>' . esc_html__( 'SATORI Audit – Archive', 'satori-audit' ) . '</h1>';

                if ( 'report_deleted' === $notice_key ) {
                        if ( $trashed > 1 ) {
                                printf(
                                        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                                        esc_html( sprintf( __( '%d SATORI Audit reports moved to Trash.', 'satori-audit' ), $trashed ) )
                                );
                        } elseif ( 1 === $trashed ) {
                                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'SATORI Audit report deleted.', 'satori-audit' ) . '</p></div>';
                        }

                        if ( $failed > 0 ) {
                                printf(
                                        '<div class="notice notice-error"><p>%s</p></div>',
                                        esc_html( sprintf( __( '%d reports could not be deleted.', 'satori-audit' ), $failed ) )
                                );
                        }
                } elseif ( 'report_delete_error' === $notice_key ) {
                        if ( $failed > 0 ) {
                                printf(
                                        '<div class="notice notice-error"><p>%s</p></div>',
                                        esc_html( sprintf( __( 'Unable to delete %d report(s). Please try again.', 'satori-audit' ), $failed ) )
                                );
                        } else {
                                echo '<div class="notice notice-error"><p>' . esc_html__( 'Unable to delete the selected report(s). Please try again.', 'satori-audit' ) . '</p></div>';
                        }
                }

                if ( $selected_report_id && $report instanceof \WP_Post ) {
                        $report_title = sprintf( esc_html__( 'Report Preview: %s', 'satori-audit' ), esc_html( $period ?: $report->post_title ) );
                        echo '<h2>' . $report_title . '</h2>';
                        include SATORI_AUDIT_PATH . 'templates/admin/report-preview.php';
                        echo '</div>';

                        return;
                }

                $query = new WP_Query(
			array(
				'post_type'      => 'satori_audit_report',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

                echo '<h2>' . esc_html__( 'Report Archive', 'satori-audit' ) . '</h2>';

                if ( ! $query->have_posts() ) {
                        echo '<p>' . esc_html__( 'No reports found. Use the Dashboard to generate your first report.', 'satori-audit' ) . '</p>';
                        echo '</div>';
                        return;
                }

                $can_manage = current_user_can( $manage_cap );
                $nonce      = wp_create_nonce( 'satori_audit_delete_report' );

                if ( $can_manage ) {
                        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                        echo '<input type="hidden" name="action" value="satori_audit_delete_report" />';
                        wp_nonce_field( 'satori_audit_delete_report', '_satori_audit_nonce' );
                }

                echo '<table class="widefat striped">';
                echo '<thead><tr>';

                if ( $can_manage ) {
                        echo '<th class="check-column"><input type="checkbox" class="satori-audit-select-all" /></th>';
                }

                echo '<th>' . esc_html__( 'Period', 'satori-audit' ) . '</th><th>' . esc_html__( 'Generated On', 'satori-audit' ) . '</th><th>' . esc_html__( 'Actions', 'satori-audit' ) . '</th></tr></thead>';
                echo '<tbody>';

                foreach ( $query->posts as $archive_post ) {
                        $archive_period = get_post_meta( $archive_post->ID, '_satori_audit_period', true );
                        $view_url       = add_query_arg(
                                array(
                                        'page'      => 'satori-audit-archive',
                                        'report_id' => $archive_post->ID,
                                ),
                                admin_url( 'admin.php' )
                        );
                        $generated_on   = mysql2date( get_option( 'date_format' ), $archive_post->post_date );

                        $delete_url = add_query_arg(
                                array(
                                        'action'              => 'satori_audit_delete_report',
                                        'report_id'           => $archive_post->ID,
                                        '_satori_audit_nonce' => $nonce,
                                ),
                                admin_url( 'admin-post.php' )
                        );

                        echo '<tr>';

                        if ( $can_manage ) {
                                echo '<th scope="row" class="check-column">';
                                echo '<input type="checkbox" name="report_ids[]" value="' . esc_attr( (string) $archive_post->ID ) . '" />';
                                echo '</th>';
                        }

                        echo '<td>' . esc_html( $archive_period ?: __( 'Unknown', 'satori-audit' ) ) . '</td>';
                        echo '<td>' . esc_html( $generated_on ) . '</td>';
                        echo '<td>';
                        echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'satori-audit' ) . '</a>';

                        if ( $can_manage ) {
                                echo ' | <a href="' . esc_url( $delete_url ) . '" class="satori-audit-delete-action">' . esc_html__( 'Delete', 'satori-audit' ) . '</a>';
                        }

                        echo '</td>';
                        echo '</tr>';
                }

                echo '</tbody></table>';

                if ( $can_manage ) {
                        echo '<script>jQuery(function($){ $(".satori-audit-select-all").on("change", function(){ $("input[name=\"report_ids[]\"]").prop("checked", $(this).is(":checked")); });});</script>';
                        echo '<div class="tablenav bottom">';
                        echo '<div class="alignleft actions">';
                        echo '<label class="screen-reader-text" for="satori-audit-bulk-action">' . esc_html__( 'Bulk actions', 'satori-audit' ) . '</label>';
                        echo '<select name="bulk_action" id="satori-audit-bulk-action">';
                        echo '<option value="">' . esc_html__( 'Bulk actions', 'satori-audit' ) . '</option>';
                        echo '<option value="delete">' . esc_html__( 'Delete selected', 'satori-audit' ) . '</option>';
                        echo '</select>';
                        submit_button( __( 'Apply', 'satori-audit' ), 'secondary', 'submit', false );
                        echo '</div>';
                        echo '</div>';
                        echo '</form>';
                }
                echo '</div>';
        }
}
