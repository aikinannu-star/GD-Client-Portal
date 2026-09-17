<?php
/**
 * GD Client Portal v2.5 - Reporting & Portfolio Analytics.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_reporting_projects($limit = 500) {
    $limit=max(1,min(1000,absint($limit)));
    if(function_exists('gdcp_service')){ $service=gdcp_service('project'); if($service && method_exists($service,'visible')) return $service->visible($limit,'',gd_client_portal_get_current_tenant_id()); }
    if(function_exists('gd_client_portal_get_visible_projects')) return gd_client_portal_get_visible_projects($limit);
    return array();
}

function gd_client_portal_reporting_health($project) {
    if (!$project) return array('score'=>0,'label'=>__('Unknown','gd-client-portal'),'class'=>'unknown');
    $score = 100;
    $sla = function_exists('gd_client_portal_get_sla') ? gd_client_portal_get_sla($project->id) : false;
    if ($sla) {
        $status = gd_client_portal_sla_status($sla);
        if ($status === 'overdue') $score -= 45;
        elseif ($status === 'at_risk') $score -= 20;
    }
    $assignments = function_exists('gd_client_portal_get_project_assignments') ? gd_client_portal_get_project_assignments($project->id) : array();
    if (empty($assignments)) $score -= 15;
    if (in_array($project->current_stage, array('revisions','revision_requested'), true)) $score -= 15;
    if (in_array($project->current_stage, array('awaiting_requirements','requirements'), true)) $score -= 5;
    $score = max(0, min(100, $score));
    if ($score >= 80) return array('score'=>$score,'label'=>__('Healthy','gd-client-portal'),'class'=>'healthy');
    if ($score >= 55) return array('score'=>$score,'label'=>__('Watch','gd-client-portal'),'class'=>'watch');
    return array('score'=>$score,'label'=>__('At risk','gd-client-portal'),'class'=>'risk');
}

function gd_client_portal_reporting_metrics($projects) {
    $metrics = array('total'=>count($projects),'active'=>0,'completed'=>0,'overdue'=>0,'at_risk'=>0,'on_track'=>0,'unassigned'=>0,'urgent'=>0,'high'=>0,'normal'=>0,'low'=>0);
    foreach ($projects as $p) {
        if (in_array($p->status, array('completed','closed'), true) || in_array($p->current_stage, array('completed','final_delivery'), true)) $metrics['completed']++;
        else $metrics['active']++;
        $sla = function_exists('gd_client_portal_get_sla') ? gd_client_portal_get_sla($p->id) : false;
        $status = $sla ? gd_client_portal_sla_status($sla) : 'none';
        if ($status === 'overdue') $metrics['overdue']++;
        elseif ($status === 'at_risk') $metrics['at_risk']++;
        elseif ($status === 'on_track') $metrics['on_track']++;
        if (!$sla) $metrics['normal']++;
        else { $priority = in_array($sla->priority, array('urgent','high','normal','low'), true) ? $sla->priority : 'normal'; $metrics[$priority]++; }
        $lead = function_exists('gd_client_portal_get_project_lead') ? gd_client_portal_get_project_lead($p->id) : false;
        if (!$lead) $metrics['unassigned']++;
    }
    return $metrics;
}

function gd_client_portal_render_reporting($atts = array()) {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_tenant_access()) return gd_client_portal_render_access_gate();
    if (!current_user_can('read')) return gd_client_portal_render_access_gate();
    wp_enqueue_style('gd-client-portal-reporting');
    wp_enqueue_script('gd-client-portal-reporting');
    $projects = gd_client_portal_reporting_projects(500);
    $metrics = gd_client_portal_reporting_metrics($projects);
    $filter = sanitize_key($_GET['report_filter'] ?? 'all');
    if (!in_array($filter, array('all','active','completed','overdue','at_risk','unassigned'), true)) $filter = 'all';
    if ($filter !== 'all') {
        $projects = array_values(array_filter($projects, function($p) use ($filter) {
            if ($filter === 'active') return !in_array($p->status, array('completed','closed'), true);
            if ($filter === 'completed') return in_array($p->status, array('completed','closed'), true) || in_array($p->current_stage, array('completed','final_delivery'), true);
            if ($filter === 'unassigned') return !gd_client_portal_get_project_lead($p->id);
            $sla = gd_client_portal_get_sla($p->id); return $sla && gd_client_portal_sla_status($sla) === $filter;
        }));
    }
    ob_start();
    echo '<div class="gd-reporting-app">';
    echo '<header class="gd-reporting-hero"><div><span>PORTFOLIO INTELLIGENCE</span><h2>'.esc_html__('Reports & Analytics','gd-client-portal').'</h2><p>'.esc_html__('A live view of delivery health, deadlines, workload coverage and portfolio performance.','gd-client-portal').'</p></div><div class="gd-reporting-actions"><a class="gd-report-export" href="'.esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=gd_client_portal_reporting_export'),'gd_client_portal_reporting_export')).'">'.esc_html__('Export CSV','gd-client-portal').'</a></div></header>';
    echo '<div class="gd-reporting-metrics">';
    $cards = array('total'=>__('Total projects','gd-client-portal'),'active'=>__('Active','gd-client-portal'),'completed'=>__('Completed','gd-client-portal'),'overdue'=>__('Overdue','gd-client-portal'),'at_risk'=>__('At risk','gd-client-portal'),'unassigned'=>__('Unassigned','gd-client-portal'));
    foreach ($cards as $key=>$label) echo '<a href="'.esc_url(add_query_arg('report_filter',$key==='total'?'all':$key)).'" class="gd-report-card metric-'.$key.'"><small>'.esc_html($label).'</small><strong>'.intval($metrics[$key]).'</strong></a>';
    echo '</div>';
    echo '<div class="gd-reporting-toolbar"><strong>'.esc_html__('Portfolio health','gd-client-portal').'</strong><div>'; foreach(array('all'=>'All','active'=>'Active','completed'=>'Completed','overdue'=>'Overdue','at_risk'=>'At risk','unassigned'=>'Unassigned') as $key=>$label) echo '<a class="'.($filter===$key?'active':''). '" href="'.esc_url(add_query_arg('report_filter',$key)).'">'.esc_html($label).'</a>'; echo '</div></div>';
    echo '<section class="gd-reporting-table"><div class="gd-reporting-table-head"><span>'.esc_html__('Project','gd-client-portal').'</span><span>'.esc_html__('Stage','gd-client-portal').'</span><span>'.esc_html__('Priority','gd-client-portal').'</span><span>'.esc_html__('SLA','gd-client-portal').'</span><span>'.esc_html__('Lead','gd-client-portal').'</span><span>'.esc_html__('Health','gd-client-portal').'</span></div>';
    if (!$projects) echo '<div class="gd-reporting-empty">'.esc_html__('No projects match this report.','gd-client-portal').'</div>';
    foreach ($projects as $p) {
        $sla = gd_client_portal_get_sla($p->id); $priority = $sla ? $sla->priority : 'normal'; $sla_status = $sla ? gd_client_portal_sla_status($sla) : 'none'; $lead = gd_client_portal_get_project_lead($p->id); $u = $lead ? gd_client_portal_cached_user($lead->user_id) : false; $health = gd_client_portal_reporting_health($p);
        echo '<article class="gd-reporting-row"><div><strong>'.esc_html($p->title).'</strong><small>#'.intval($p->id).'</small></div><span>'.esc_html(ucwords(str_replace('_',' ',$p->current_stage))).'</span><span class="priority-'.$priority.'">'.esc_html(ucfirst($priority)).'</span><span class="sla-'.$sla_status.'">'.esc_html($sla ? gd_client_portal_sla_remaining_label($sla->due_date) : __('Not set','gd-client-portal')).'</span><span>'.esc_html($u ? $u->display_name : __('Unassigned','gd-client-portal')).'</span><span class="health-'.$health['class'].'"><b>'.intval($health['score']).'</b> '.esc_html($health['label']).'</span></article>';
    }
    echo '</section></div>';
    return ob_get_clean();
}

function gd_client_portal_reporting_export_ajax() {
    if (!is_user_logged_in() || !current_user_can('read')) wp_die(esc_html__('Access denied.','gd-client-portal'), '', array('response'=>403));
    check_admin_referer('gd_client_portal_reporting_export');
    if (!gd_client_portal_verify_tenant_access()) wp_die(esc_html__('Access denied.','gd-client-portal'), '', array('response'=>403));
    $projects = gd_client_portal_reporting_projects(1000);
    nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="gd-client-portal-report-'.gmdate('Y-m-d').'.csv"');
    $out = fopen('php://output','w'); fputcsv($out,array('Project ID','Project','Status','Stage','Priority','SLA Status','Due Date','Stage Due Date','Lead','Health Score','Health Label','Created At'));
    foreach($projects as $p){$sla=gd_client_portal_get_sla($p->id);$lead=gd_client_portal_get_project_lead($p->id);$u=$lead?gd_client_portal_cached_user($lead->user_id):false;$h=gd_client_portal_reporting_health($p);fputcsv($out,array($p->id,$p->title,$p->status,$p->current_stage,$sla?$sla->priority:'normal',$sla?gd_client_portal_sla_status($sla):'none',$sla?$sla->due_date:'',$sla?$sla->stage_due_date:'',$u?$u->display_name:'',$h['score'],$h['label'],$p->created_at));} fclose($out); exit;
}
add_action('wp_ajax_gd_client_portal_reporting_export','gd_client_portal_reporting_export_ajax');
add_shortcode('gd_portfolio_reports','gd_client_portal_render_reporting');
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view')) gd_client_portal_register_dashboard_view('reports','gd_client_portal_render_reporting');});
function gd_client_portal_register_reporting_assets(){wp_register_style('gd-client-portal-reporting',GD_CLIENT_PORTAL_URL.'assets/reporting.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);wp_register_script('gd-client-portal-reporting',GD_CLIENT_PORTAL_URL.'assets/reporting.js',array(),GD_CLIENT_PORTAL_VERSION,true);}
add_action('wp_enqueue_scripts','gd_client_portal_register_reporting_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_reporting_assets');
function gd_client_portal_register_reporting_menu(){add_submenu_page('gd-client-portal',__('Reports & Analytics','gd-client-portal'),__('Reports & Analytics','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-reports','gd_client_portal_render_reporting_admin');}
add_action('admin_menu','gd_client_portal_register_reporting_menu',20);
function gd_client_portal_render_reporting_admin(){if(!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal'));echo '<div class="wrap gd-reporting-admin-wrap">'.gd_client_portal_render_reporting().'</div>';}
