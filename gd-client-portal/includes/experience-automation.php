<?php
/**
 * GD Client Portal v5.9 — Client Experience Automation.
 * Connects experience intelligence to the workflow engine with conservative safeguards.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_experience_automation_threshold() { return 60; }
function gd_client_portal_experience_automation_payload($project, $score) {
    return array('project_id'=>absint($project->id),'tenant_id'=>absint($project->tenant_id),'experience_score'=>absint($score['score']),'experience_level'=>sanitize_key($score['level']),'experience_signals'=>(array)$score['signals'],'experience_recommendations'=>(array)$score['recommendations']);
}
function gd_client_portal_experience_automation_run_project($project, $force=false) {
    if (!$project || !function_exists('gd_client_portal_experience_score_project')) return array();
    $score=gd_client_portal_experience_score_project($project); $threshold=gd_client_portal_experience_automation_threshold();
    if (!$force && (int)$score['score']>$threshold) return array();
    $key='gd_client_portal_experience_alert_'.absint($project->id); $last=get_transient($key);
    if (!$force && $last) return array();
    $payload=gd_client_portal_experience_automation_payload($project,$score);
    $result=function_exists('gd_client_portal_automation_run')?gd_client_portal_automation_run('client_experience_at_risk',$payload):array();
    set_transient($key,1,DAY_IN_SECONDS);
    do_action('gd_client_portal_client_experience_at_risk',$project,$score,$result);
    return $result;
}
function gd_client_portal_experience_automation_daily() {
    if (!function_exists('gd_client_portal_get_visible_projects')) return;
    foreach ((array)gd_client_portal_get_visible_projects() as $project) gd_client_portal_experience_automation_run_project($project,false);
}
add_action('gd_client_portal_sla_daily','gd_client_portal_experience_automation_daily',30);
add_action('gd_client_portal_project_stage_changed',function($project_id){$p=function_exists('gd_client_portal_get_project_by_id')?gd_client_portal_get_project_by_id($project_id):false;if($p)gd_client_portal_experience_automation_run_project($p,false);},30,1);
add_action('gd_client_portal_feedback_submitted',function($review_id){$r=function_exists('gdcp_feedback_service')?gdcp_feedback_service()->get_review_for_access($review_id):null;if($r&&$r->project_id&&function_exists('gd_client_portal_get_project_by_id')){$p=gd_client_portal_get_project_by_id($r->project_id);if($p)gd_client_portal_experience_automation_run_project($p,true);}},30,1);
add_action('plugins_loaded',function(){
    if (function_exists('gd_client_portal_automation_events')) {
        // Event is declared by the automation module; this hook is intentionally inert if an older engine is present.
    }
},31);
