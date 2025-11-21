<?php
/**
 * Admin report preview template.
 *
 * Variables expected: $report_id, $period, $plugin_rows, $security_rows, $meta, $summary, $settings.
 *
 * @package Satori_Audit
 */

$settings = $settings ?? \Satori_Audit\Includes\Satori_Audit_Plugin::get_settings();
$meta      = $meta ?? [];
$summary   = $summary ?? [];
$period    = $period ?? '';
$plugin_rows  = $plugin_rows ?? [];
$security_rows = $security_rows ?? [];
$service_details = $settings;
$overview  = $meta['overview'] ?? [ 'security' => '', 'general' => '', 'misc' => '', 'comments' => '' ];
?>
<div class="satori-audit-preview">
    <header class="satori-audit-header">
        <div>
            <h2><?php echo esc_html( $settings['site_name'] ?: get_bloginfo( 'name' ) ); ?></h2>
            <p><?php echo esc_html( $settings['site_url'] ?: home_url() ); ?></p>
            <p><?php echo esc_html( $settings['managed_by'] ); ?></p>
        </div>
        <div class="satori-audit-meta">
            <p><strong><?php esc_html_e( 'Period', 'satori-audit' ); ?>:</strong> <?php echo esc_html( $period ); ?></p>
            <p><strong><?php esc_html_e( 'Client', 'satori-audit' ); ?>:</strong> <?php echo esc_html( $settings['client_name'] ); ?></p>
            <p><strong><?php esc_html_e( 'Technician', 'satori-audit' ); ?>:</strong> <?php echo esc_html( $service_details['technician_name'] ?? '' ); ?></p>
        </div>
    </header>

    <section class="satori-audit-overview">
        <h3><?php esc_html_e( 'Overview', 'satori-audit' ); ?></h3>
        <div class="satori-audit-columns">
            <div>
                <h4><?php esc_html_e( 'Security', 'satori-audit' ); ?></h4>
                <p><?php echo nl2br( esc_html( $overview['security'] ?? '' ) ); ?></p>
            </div>
            <div>
                <h4><?php esc_html_e( 'General', 'satori-audit' ); ?></h4>
                <p><?php echo nl2br( esc_html( $overview['general'] ?? '' ) ); ?></p>
            </div>
            <div>
                <h4><?php esc_html_e( 'Misc', 'satori-audit' ); ?></h4>
                <p><?php echo nl2br( esc_html( $overview['misc'] ?? '' ) ); ?></p>
            </div>
        </div>
        <div>
            <h4><?php esc_html_e( 'Comments', 'satori-audit' ); ?></h4>
            <p><?php echo nl2br( esc_html( $overview['comments'] ?? '' ) ); ?></p>
        </div>
    </section>

    <section class="satori-audit-summary">
        <h3><?php esc_html_e( 'Plugin Summary', 'satori-audit' ); ?></h3>
        <ul class="satori-audit-counters">
            <li><?php printf( esc_html__( '%d New', 'satori-audit' ), (int) ( $summary['new'] ?? 0 ) ); ?></li>
            <li><?php printf( esc_html__( '%d Updated', 'satori-audit' ), (int) ( $summary['updated'] ?? 0 ) ); ?></li>
            <li><?php printf( esc_html__( '%d Deleted', 'satori-audit' ), (int) ( $summary['deleted'] ?? 0 ) ); ?></li>
            <li><?php printf( esc_html__( '%d Unchanged', 'satori-audit' ), (int) ( $summary['unchanged'] ?? 0 ) ); ?></li>
        </ul>
        <p class="legend"><?php echo esc_html( $meta['legend'] ?? __( 'NEW = newly installed, UPDATED = version change, DELETED = removed since last audit.', 'satori-audit' ) ); ?></p>
    </section>

    <section class="satori-audit-plugins">
        <h3><?php esc_html_e( 'Plugins', 'satori-audit' ); ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Status', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Plugin', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Version', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Notes', 'satori-audit' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $plugin_rows ) ) : ?>
                    <tr><td colspan="6" class="empty"><?php esc_html_e( 'No plugin data available.', 'satori-audit' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $plugin_rows as $row ) : ?>
                        <tr>
                            <td class="status status-<?php echo esc_attr( $row['status_flag'] ); ?>"><?php echo esc_html( strtoupper( $row['status_flag'] ) ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $row['plugin_name'] ); ?></strong>
                                <div class="description"><?php echo esc_html( wp_trim_words( (string) $row['plugin_description'], 20 ) ); ?></div>
                            </td>
                            <td>
                                <?php echo esc_html( $row['version_current'] ); ?>
                                <?php if ( $row['version_from'] && $row['version_to'] ) : ?>
                                    <div class="muted"><?php printf( esc_html__( 'from %1$s to %2$s', 'satori-audit' ), esc_html( $row['version_from'] ), esc_html( $row['version_to'] ) ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $row['is_active'] ? esc_html__( 'Yes', 'satori-audit' ) : esc_html__( 'No', 'satori-audit' ); ?></td>
                            <td><?php echo esc_html( $row['plugin_type'] ); ?></td>
                            <td>
                                <?php echo nl2br( esc_html( $row['price_notes'] ) ); ?>
                                <?php if ( ! empty( $row['comments'] ) ) : ?>
                                    <div class="muted"><?php echo nl2br( esc_html( $row['comments'] ) ); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <?php if ( ! empty( $settings['show_security'] ) ) : ?>
    <section class="satori-audit-security">
        <h3><?php esc_html_e( 'Security & Known Issues', 'satori-audit' ); ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Type', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Severity', 'satori-audit' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'satori-audit' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $security_rows ) ) : ?>
                    <tr><td colspan="4" class="empty"><?php esc_html_e( 'No security findings recorded.', 'satori-audit' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $security_rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['vulnerability_type'] ); ?></td>
                            <td>
                                <?php echo nl2br( esc_html( $row['description'] ) ); ?>
                                <?php if ( ! empty( $row['solution'] ) ) : ?>
                                    <div class="muted"><?php esc_html_e( 'Solution:', 'satori-audit' ); ?> <?php echo nl2br( esc_html( $row['solution'] ) ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $row['severity'] ); ?><?php echo $row['cvss_score'] ? ' (' . esc_html( $row['cvss_score'] ) . ')' : ''; ?></td>
                            <td><?php echo ! empty( $row['action_required'] ) ? esc_html__( 'Action required', 'satori-audit' ) : esc_html__( 'Monitor', 'satori-audit' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <?php if ( ! empty( $settings['footer_text'] ) ) : ?>
        <footer class="satori-audit-footer"><?php echo esc_html( $settings['footer_text'] ); ?></footer>
    <?php endif; ?>
</div>
