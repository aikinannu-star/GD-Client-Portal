<?php
if (!defined('ABSPATH')) exit;

function gd_client_portal_predictive_score_project($project) {
    if (!$project) return array('score'=>0,'level'=>'unknown','signals'=>array());
    $score=0; $signals=array(); $stage=(string)($project->current_stage??''); $status=(string)($project->status??'');
    global $wpdb;
    if (function_exists('gdcp_sla_service')) {
        $sla=gdcp_sla_service()->get(absint($project->id));
        if ($sla && !empty($sla->due_date)) {
            $days=(strtotime($sla->due_date)-current_time('timestamp'))/DAY_IN_SECONDS;
            if ($days < 0) {$score+=45;$signals[]='Project SLA is overdue.';}
            elseif ($days <= 2) {$score+=30;$signals[]='Project SLA is due within 2 days.';}
            elseif ($days <= 5) {$score+=15;$signals[]='Project SLA is due within 5 days.';}
        }
    }
    if (in_array($stage,array('awaiting_requirements','requirements','requirements_analysis'),true)) {$score+=12;$signals[]='Project is still awaiting requirements.';}
    $lead=function_exists('gd_client_portal_get_project_lead')?gd_client_portal_get_project_lead($project->id):false;
    if (!$lead) {$score+=15;$signals[]='Project has no team lead assigned.';}
    if (in_array($status,array('active','in_progress','awaiting_requirements'),true) && $stage==='revisions') {$score+=20;$signals[]='Project is in revisions.';}
    $score=min(100,$score); $level=$score>=60?'high':($score>=30?'medium':'low');
    return array('score'=>$score,'level'=>$level,'signals'=>$signals);
}

function gd_client_portal_predictive_projects($limit=100){
    if (!function_exists('gd_client_portal_get_visible_projects')) return array();
    $projects=gd_client_portal_get_visible_projects(); $out=array();
    foreach((array)$projects as $p){$r=gd_client_portal_predictive_score_project($p);$r['project']=$p;$out[]=$r;}
    usort($out,function($a,$b){return $b['score']<=>$a['score'];}); return array_slice($out,0,absint($limit));
}

function gd_client_portal_predictive_admin(){
    if (!gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $items=gd_client_portal_predictive_projects(100); $high=$medium=$low=0; foreach($items as $i){${$i['level']}++;}
    echo '<div class="wrap"><h1>Predictive Workflow Intelligence</h1><p>Risk signals are calculated from current portal data such as SLA proximity, assignment, requirements and revision state. This is operational forecasting, not machine-learning certainty.</p>';
    echo '<div class="gdcp-predictive-cards"><div><b>'.intval($high).'</b><span>High risk</span></div><div><b>'.intval($medium).'</b><span>Medium risk</span></div><div><b>'.intval($low).'</b><span>Low risk</span></div></div>';
    echo '<table class="widefat striped"><thead><tr><th>Project</th><th>Risk</th><th>Score</th><th>Signals</th></tr></thead><tbody>';
    foreach($items as $i){$p=$i['project'];echo '<tr><td><strong>#'.intval($p->id).'</strong> '.esc_html($p->title??'Project').'</td><td>'.esc_html(ucfirst($i['level'])).'</td><td><strong>'.intval($i['score']).'/100</strong></td><td>'.esc_html(implode(' ',$i['signals'])?:'No major risk signals.').'</td></tr>';}
    if(!$items) echo '<tr><td colspan="4">No visible projects available for forecasting.</td></tr>';
    echo '</tbody></table></div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Predictive Workflow Intelligence','Predictive Intelligence','gd_client_portal_access_admin','gd-client-portal-predictive','gd_client_portal_predictive_admin');},20);
add_shortcode('gd_predictive_intelligence','gd_client_portal_predictive_admin');
