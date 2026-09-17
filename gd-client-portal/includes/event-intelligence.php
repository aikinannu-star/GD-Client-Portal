<?php
/** GD Client Portal v4.8 - Event Intelligence & Workflow Analytics. */
if (!defined('ABSPATH')) exit;

function gd_client_portal_event_intelligence_data($days = 30) {
    $d=gdcp_automation_service()->event_analytics($days);
    $d['success_rate']=$d['total']?round($d['success']/$d['total']*100,1):100;
    return $d;
}

function gd_client_portal_event_intelligence_admin() {
    if (!gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $d = gd_client_portal_event_intelligence_data(isset($_GET['days']) ? absint($_GET['days']) : 30);
    $labels = function_exists('gd_client_portal_automation_events') ? gd_client_portal_automation_events() : array();
    echo '<div class="wrap"><h1>Event Intelligence &amp; Workflow Analytics</h1><p>Understand which events drive automation, where failures occur, and where workflow capacity is being consumed.</p>';
    echo '<p><a class="button '.($d['days']===7?'button-primary':'').'" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-event-intelligence&days=7')).'">7 days</a> <a class="button '.($d['days']===30?'button-primary':'').'" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-event-intelligence&days=30')).'">30 days</a> <a class="button '.($d['days']===90?'button-primary':'').'" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-event-intelligence&days=90')).'">90 days</a></p>';
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0">';
    foreach(array('Events processed'=>$d['total'],'Success rate'=>$d['success_rate'].'%','Failures'=>$d['failed'],'Avg action time'=>round($d['avg_ms']).' ms') as $k=>$v) echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><strong style="font-size:25px">'.esc_html($v).'</strong><br>'.esc_html($k).'</div>';
    echo '</div>';
    echo '<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;align-items:start">';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><h2 style="margin-top:0">Event performance</h2><table class="widefat striped"><thead><tr><th>Event</th><th>Runs</th><th>Success</th><th>Failed</th><th>Success rate</th><th>Avg ms</th><th>Last run</th></tr></thead><tbody>';
    foreach((array)$d['rows'] as $r){$rate=$r->runs?round((int)$r->success/(int)$r->runs*100,1):100;echo '<tr><td>'.esc_html($labels[$r->event_key]??$r->event_key).'</td><td>'.intval($r->runs).'</td><td>'.intval($r->success).'</td><td>'.intval($r->failed).'</td><td>'.esc_html($rate).'%</td><td>'.esc_html(round((float)$r->avg_ms)).'</td><td>'.esc_html($r->last_run).'</td></tr>';}
    if(!$d['rows']) echo '<tr><td colspan="7">No automation event data for this period.</td></tr>';
    echo '</tbody></table></section>';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px"><h2 style="margin-top:0">Bottleneck signals</h2><ul style="line-height:1.9">';
    $signals=array();
    if($d['failed']) $signals[]='<strong>'.$d['failed'].'</strong> failed automation executions require review.';
    foreach((array)$d['top_failures'] as $f){$signals[]='<strong>'.intval($f->failures).'</strong> failures from <code>'.esc_html($labels[$f->event_key]??$f->event_key).'</code>.';}
    if($d['avg_ms']>5000) $signals[]='Average action duration is above <strong>5 seconds</strong>; inspect slow actions and external integrations.';
    if(!$signals) $signals[]='No major event-level bottleneck detected.';
    foreach($signals as $s) echo '<li>'.$s.'</li>';
    echo '</ul><p><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-insights')).'">Workflow Intelligence</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-reliability')).'">Reliability Center</a></p></section></div>';
    echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;margin-top:18px"><h2>Daily activity</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Events</th><th>Success</th><th>Failed</th><th>Rate</th></tr></thead><tbody>';
    foreach((array)$d['daily'] as $r){$rate=$r->runs?round((int)$r->success/(int)$r->runs*100,1):100;echo '<tr><td>'.esc_html($r->day).'</td><td>'.intval($r->runs).'</td><td>'.intval($r->success).'</td><td>'.intval($r->failed).'</td><td>'.esc_html($rate).'%</td></tr>';}
    if(!$d['daily']) echo '<tr><td colspan="5">No daily activity recorded.</td></tr>';
    echo '</tbody></table></section></div>';
}
add_action('admin_menu', function(){ add_submenu_page('gd-client-portal','Event Intelligence','Event Intelligence','gd_client_portal_access_admin','gd-client-portal-event-intelligence','gd_client_portal_event_intelligence_admin'); }, 25);
add_shortcode('gd_event_intelligence','gd_client_portal_event_intelligence_admin');
