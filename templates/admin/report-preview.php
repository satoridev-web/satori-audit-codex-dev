<?php
/**
 * Admin report preview template.
 *
 * @package Satori_Audit
 */

$report_id = $report_id ?? ( $selected_report_id ?? 0 );

echo \Satori_Audit\Reports::get_report_html( (int) $report_id );
