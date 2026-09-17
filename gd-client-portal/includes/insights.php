<?php
/**
 * GD Client Portal v4.4 - Workflow Intelligence & Optimization.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_workflow_insights_data() {
    $d=gdcp_automation_service()->analytics(30);
    $d['success_rate']=$d['runs']?round(($d['success']/$d['runs'])*100,1):100;
    return $d;
}

function gd_client_portal_workflow_insights_admin() {
    if (!gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $d = gd_client_portal_workflow_insights_data();
    $events = gd_client_portal_automation_events();
    echo '<div class="wrap"><h1>Workflow Intelligence</h1><p>30-day automation performance, bottlenecks and operational recommendations.</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:20px 0">';
    $cards=array('Runs'=>$d['runs'],'Success rate'=>$d['success_rate'].'%','Failed runs'=>$d['failed'],'Queued'=>$d['queued'],'Failed queue'=>$d['failed_queue'],'Active rules'=>$d['active_rules']);
    foreach($cards as $k=>$v) echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><strong style="font-size:25px">'.esc_html($v).'</strong><br><span>'.esc_html($k).'</span></div>';
    echo '</div>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px">';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><h2 style="margin-top:0">Optimization signals</h2><ul style="line-height:1.9">';
    $signals=array();
    if($d['failed_queue'])$signals[]='<strong>'.$d['failed_queue'].'</strong> queued actions are currently failed — review and retry them.';
    if($d['old_failed'])$signals[]='<strong>'.$d['old_failed'].'</strong> failed queue items are older than 24 hours — investigate them as dead-letter candidates.';
    if($d['stale'])$signals[]='<strong>'.$d['stale'].'</strong> queue items have been running for more than 30 minutes — inspect for stalled cron execution.';
    if($d['runs']>=20 && $d['success_rate']<95)$signals[]='Automation success rate is below <strong>95%</strong>. Review the highest-failure triggers below.';
    if(!$signals)$signals[]='No major operational warning detected in the last 30 days.';
    foreach($signals as $s)echo '<li>'.$s.'</li>';
    echo '</ul><p><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-governance')).'">Open Governance</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-orchestration')).'">Open Orchestration</a></p></section>';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><h2 style="margin-top:0">Trigger performance</h2><table class="widefat striped"><thead><tr><th>Trigger</th><th>Runs</th><th>Success</th><th>Failed</th><th>Rate</th></tr></thead><tbody>';
    foreach((array)$d['events'] as $e){$rate=$e->runs?round(((int)$e->success/(int)$e->runs)*100,1):100;echo '<tr><td>'.esc_html($events[$e->event_key]??$e->event_key).'</td><td>'.intval($e->runs).'</td><td>'.intval($e->success).'</td><td>'.intval($e->failed).'</td><td>'.esc_html($rate).'%</td></tr>';}
    if(!$d['events'])echo '<tr><td colspan="5">No executions recorded in the last 30 days.</td></tr>';
    echo '</tbody></table></section></div>';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin-top:18px"><h2>Workflow performance</h2><table class="widefat striped"><thead><tr><th>Workflow</th><th>Runs</th><th>Success</th><th>Failed</th><th>Rate</th><th>Last run</th></tr></thead><tbody>';
    foreach((array)$d['rule_stats'] as $s){$r=gd_client_portal_automation_get_rule($s->rule_id);$rate=$s->runs?round(((int)$s->success/(int)$s->runs)*100,1):100;echo '<tr><td>'.esc_html($r?$r->name:'Deleted workflow').' (#'.intval($s->rule_id).')</td><td>'.intval($s->runs).'</td><td>'.intval($s->success).'</td><td>'.intval($s->failed).'</td><td>'.esc_html($rate).'%</td><td>'.esc_html($s->last_run).'</td></tr>';}
    if(!$d['rule_stats'])echo '<tr><td colspan="6">No workflow execution data available.</td></tr>';
    echo '</tbody></table></section></div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Workflow Intelligence','Workflow Intelligence','gd_client_portal_access_admin','gd-client-portal-insights','gd_client_portal_workflow_insights_admin');},23);
add_shortcode('gd_workflow_intelligence','gd_client_portal_workflow_insights_admin');
