<?php
/**
 * Admin report preview template.
 *
 * @package Satori_Audit
 */

$period      = $period ?? '';
$plugin_rows = $plugin_rows ?? array();
$report_date = isset( $report->post_date ) ? mysql2date( get_option( 'date_format' ), $report->post_date ) : '';
?>
<div class="satori-audit-preview">
        <header class="satori-audit-header">
                <h2><?php echo esc_html( $period ? sprintf( __( 'Audit Report – %s', 'satori-audit' ), $period ) : __( 'Audit Report', 'satori-audit' ) ); ?></h2>
                <?php if ( $report_date ) : ?>
                        <p><?php esc_html_e( 'Generated on', 'satori-audit' ); ?>: <?php echo esc_html( $report_date ); ?></p>
                <?php endif; ?>
        </header>

        <section class="satori-audit-plugins">
                <h3><?php esc_html_e( 'Plugins', 'satori-audit' ); ?></h3>
                <table class="widefat striped">
                        <thead>
                                <tr>
                                        <th><?php esc_html_e( 'Plugin', 'satori-audit' ); ?></th>
                                        <th><?php esc_html_e( 'Version', 'satori-audit' ); ?></th>
                                        <th><?php esc_html_e( 'Active', 'satori-audit' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php if ( empty( $plugin_rows ) ) : ?>
                                        <tr><td colspan="3" class="empty"><?php esc_html_e( 'No plugin data available.', 'satori-audit' ); ?></td></tr>
                                <?php else : ?>
                                        <?php foreach ( $plugin_rows as $row ) : ?>
                                                <tr>
                                                        <td>
                                                                <strong><?php echo esc_html( $row['plugin_name'] ); ?></strong>
                                                                <?php if ( ! empty( $row['plugin_description'] ) ) : ?>
                                                                        <div class="description"><?php echo esc_html( $row['plugin_description'] ); ?></div>
                                                                <?php endif; ?>
                                                        </td>
                                                        <td><?php echo esc_html( $row['version_current'] ); ?></td>
                                                        <td><?php echo ! empty( $row['is_active'] ) ? esc_html__( 'Yes', 'satori-audit' ) : esc_html__( 'No', 'satori-audit' ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                        </tbody>
                </table>
        </section>
</div>
