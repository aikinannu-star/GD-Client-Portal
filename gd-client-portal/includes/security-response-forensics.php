<?php
/** Security response analytics and bounded forensic evidence views. */
if (!defined('ABSPATH')) exit;

function gdcp_security_forensics_can_access($user_id = 0) {
    $user_id = absint($user_id ?: get_current_user_id());
    return $user_id && (user_can($user_id, 'manage_options') || (user_can($user_id, 'gd_client_portal_tenant_admin') && function_exists('gd_client_portal_get_user_tenant_id') && gd_client_portal_get_user_tenant_id($user_id)));
}

function gdcp_security_forensics_scope($user_id = 0) {
    $user_id = absint($user_id ?: get_current_user_id());
    if (!$user_id || !gdcp_security_forensics_can_access($user_id)) return array('tenant_id' => 0, 'global' => false);
    if (user_can($user_id, 'manage_options')) return array('tenant_id' => 0, 'global' => true);
    return array('tenant_id' => absint(gd_client_portal_get_user_tenant_id($user_id)), 'global' => false);
}

function gdcp_security_forensics_summary($hours = 24, $user_id = 0) {
    global $wpdb;
    $scope = gdcp_security_forensics_scope($user_id); if (!$scope['global'] && !$scope['tenant_id']) return array();
    $hours = max(1, min(720, absint($hours))); $where = 'event_time >= %s'; $args = array(gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS));
    if (!$scope['global']) { $where .= ' AND tenant_id = %d'; $args[] = $scope['tenant_id']; }
    $t = gdcp_security_audit_repository()->table();
    $sql = "SELECT COUNT(*) total, COUNT(DISTINCT user_id) users, COUNT(DISTINCT tenant_id) tenants, COUNT(DISTINCT correlation_id) correlations, SUM(CASE WHEN type='authorization_denied' THEN 1 ELSE 0 END) denials, SUM(CASE WHEN severity IN ('critical','high') THEN 1 ELSE 0 END) high_events FROM {$t} WHERE {$where}";
    return (array) $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_A);
}

function gdcp_security_forensics_breakdown($dimension = 'user_id', $hours = 24, $limit = 10, $user_id = 0) {
    global $wpdb; $scope = gdcp_security_forensics_scope($user_id); if (!$scope['global'] && !$scope['tenant_id']) return array();
    $allowed = array('user_id','tenant_id','correlation_id','action','object_type'); if (!in_array($dimension, $allowed, true)) return array();
    $hours = max(1, min(720, absint($hours))); $limit = min(50, max(1, absint($limit))); $where = 'event_time >= %s'; $args = array(gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS));
    if (!$scope['global']) { $where .= ' AND tenant_id = %d'; $args[] = $scope['tenant_id']; }
    $t = gdcp_security_audit_repository()->table();
    $sql = "SELECT {$dimension} dimension_value, COUNT(*) event_count, COUNT(DISTINCT user_id) users, COUNT(DISTINCT tenant_id) tenants, MAX(event_time) last_seen FROM {$t} WHERE {$where} GROUP BY {$dimension} ORDER BY event_count DESC LIMIT {$limit}";
    return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
}

function gdcp_security_forensics_timeline($hours = 24, $bucket_minutes = 60, $user_id = 0) {
    global $wpdb; $scope = gdcp_security_forensics_scope($user_id); if (!$scope['global'] && !$scope['tenant_id']) return array();
    $hours = max(1, min(720, absint($hours))); $bucket_minutes = max(5, min(1440, absint($bucket_minutes))); $where = 'event_time >= %s'; $args = array(gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS));
    if (!$scope['global']) { $where .= ' AND tenant_id = %d'; $args[] = $scope['tenant_id']; }
    $t = gdcp_security_audit_repository()->table();
    // UNIX epoch bucketing is portable across supported MySQL/MariaDB versions.
    $bucket = "FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(event_time)/" . ($bucket_minutes * 60) . ")*" . ($bucket_minutes * 60) . ")";
    $sql = "SELECT {$bucket} bucket_start, COUNT(*) event_count, SUM(CASE WHEN type='authorization_denied' THEN 1 ELSE 0 END) denials, COUNT(DISTINCT user_id) users, COUNT(DISTINCT tenant_id) tenants FROM {$t} WHERE {$where} GROUP BY {$bucket} ORDER BY bucket_start ASC";
    return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
}

function gdcp_security_forensics_events($filters = array(), $limit = 100, $user_id = 0) {
    $scope = gdcp_security_forensics_scope($user_id); if (!$scope['global'] && !$scope['tenant_id']) return array();
    if (!$scope['global']) $filters['tenant_id'] = $scope['tenant_id'];
    return gdcp_security_audit_repository()->investigate($filters, min(200, max(1, absint($limit))));
}

function gdcp_security_forensics_export() {
    if (!gdcp_security_forensics_can_access()) wp_die(esc_html__('Unauthorized','gd-client-portal'), '', array('response'=>403));
    check_admin_referer('gdcp_security_forensics_export');
    $hours = max(1, min(720, absint($_GET['hours'] ?? 24))); $rows = gdcp_security_forensics_events(array('from'=>gmdate('Y-m-d H:i:s', time()-$hours*HOUR_IN_SECONDS)), 200);
    nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="gdcp-security-evidence-' . gmdate('Ymd-His') . '.csv"');
    $out = fopen('php://output','w'); fputcsv($out,array('event_time','type','severity','action','object_type','object_id','tenant_id','user_id','project_id','correlation_id','message','event_hash'));
    foreach ((array)$rows as $r) fputcsv($out,array($r->event_time,$r->type,$r->severity,$r->action,$r->object_type,$r->object_id,$r->tenant_id,$r->user_id,$r->project_id,$r->correlation_id,$r->message,$r->event_hash));
    fclose($out); exit;
}

function gdcp_security_forensics_page() {
    if (!gdcp_security_forensics_can_access()) wp_die(esc_html__('You are not authorized to view security forensics.','gd-client-portal'), '', array('response'=>403));
    $hours = max(1, min(720, absint($_GET['hours'] ?? 24))); $summary=gdcp_security_forensics_summary($hours); $timeline=gdcp_security_forensics_timeline($hours,60); $users=gdcp_security_forensics_breakdown('user_id',$hours,10); $tenants=gdcp_security_forensics_breakdown('tenant_id',$hours,10); $corr=gdcp_security_forensics_breakdown('correlation_id',$hours,10);
    echo '<div class="wrap"><h1>Security Response Analytics &amp; Forensics</h1><p>Bounded, tenant-scoped evidence analysis from the persistent security event stream.</p><form method="get"><input type="hidden" name="page" value="gd-client-portal-security-forensics"><select name="hours"><option value="1"'.selected($hours,1,false).'>1 hour</option><option value="24"'.selected($hours,24,false).'>24 hours</option><option value="168"'.selected($hours,168,false).'>7 days</option><option value="720"'.selected($hours,720,false).'>30 days</option></select> '; submit_button('Refresh','secondary','submit',false); echo '</form>';
    $labels=array('total'=>'Events','denials'=>'Authorization denials','high_events'=>'High/Critical events','users'=>'Affected users','tenants'=>'Affected tenants','correlations'=>'Correlations'); echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">'; foreach($labels as $k=>$label) echo '<div style="min-width:145px;padding:14px;border:1px solid #ccd0d4;background:#fff"><strong>'.esc_html($label).'</strong><br><span style="font-size:22px">'.esc_html($summary[$k]??0).'</span></div>'; echo '</div>';
    $export=wp_nonce_url(admin_url('admin.php?page=gd-client-portal-security-forensics&gdcp_forensics_export=1&hours='.$hours),'gdcp_security_forensics_export'); echo '<p><a class="button" href="'.esc_url($export).'">Export evidence CSV</a></p>';
    gdcp_security_forensics_table('Timeline',$timeline,array('bucket_start'=>'Bucket','event_count'=>'Events','denials'=>'Denials','users'=>'Users','tenants'=>'Tenants'));
    gdcp_security_forensics_table('Top affected users',$users,array('dimension_value'=>'User ID','event_count'=>'Events','users'=>'Users','tenants'=>'Tenants','last_seen'=>'Last seen'));
    gdcp_security_forensics_table('Affected tenants',$tenants,array('dimension_value'=>'Tenant ID','event_count'=>'Events','users'=>'Users','tenants'=>'Tenants','last_seen'=>'Last seen'));
    gdcp_security_forensics_table('Correlation clusters',$corr,array('dimension_value'=>'Correlation','event_count'=>'Events','users'=>'Users','tenants'=>'Tenants','last_seen'=>'Last seen'));
    echo '</div>';
}
function gdcp_security_forensics_table($title,$rows,$columns){ echo '<h2>'.esc_html($title).'</h2><table class="widefat striped"><thead><tr>'; foreach($columns as $label) echo '<th>'.esc_html($label).'</th>'; echo '</tr></thead><tbody>'; if(!$rows) echo '<tr><td colspan="'.count($columns).'">No evidence in the selected window.</td></tr>'; foreach($rows as $r){echo '<tr>'; foreach($columns as $key=>$label) echo '<td>'.esc_html((string)($r[$key]??'')).'</td>'; echo '</tr>';} echo '</tbody></table>'; }

add_action('admin_menu', function(){ add_submenu_page('gd-client-portal','Security Response Analytics & Forensics','Security Forensics','manage_options','gd-client-portal-security-forensics','gdcp_security_forensics_page'); },37);
add_action('admin_init', function(){ if(!empty($_GET['gdcp_forensics_export'])) gdcp_security_forensics_export(); });
