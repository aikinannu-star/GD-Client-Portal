<?php
/**
 * GD Client Portal v2.3 - SLA, Deadlines & Escalation Management.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_sla_table() { global $wpdb; return $wpdb->prefix . 'gd_project_slas'; }

function gd_client_portal_activate_sla_table() {
    global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table=gd_client_portal_sla_table(); $charset=$wpdb->get_charset_collate();
    $sql="CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        project_id bigint(20) unsigned NOT NULL, tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        priority varchar(20) NOT NULL DEFAULT 'normal', due_date datetime NULL, stage_due_date datetime NULL,
        warning_days int(11) unsigned NOT NULL DEFAULT 2, escalation_level tinyint(3) unsigned NOT NULL DEFAULT 0,
        last_escalated_at datetime NULL, updated_by bigint(20) unsigned NOT NULL DEFAULT 0, updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), UNIQUE KEY project_id(project_id), KEY tenant_id(tenant_id), KEY due_date(due_date), KEY stage_due_date(stage_due_date), KEY priority(priority)
    ) {$charset};"; dbDelta($sql);
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_sla_priority_days($priority='normal') { return array('urgent'=>3,'high'=>7,'normal'=>14,'low'=>21)[$priority] ?? 14; }
function gd_client_portal_sla_stage_days($priority='normal') { return array('urgent'=>1,'high'=>2,'normal'=>3,'low'=>5)[$priority] ?? 3; }

function gd_client_portal_get_sla($project_id) { return gdcp_sla_service()->get($project_id); }
function gd_client_portal_sla_cache_clear($project_id=0) { return true; }
function gd_client_portal_ensure_project_sla($project) { return gdcp_sla_service()->ensure($project); }
function gd_client_portal_sla_status($sla) {
    if(!$sla) return 'none'; $now=time(); $due=!empty($sla->due_date)?strtotime($sla->due_date):0; $stage=!empty($sla->stage_due_date)?strtotime($sla->stage_due_date):0;
    if(($due && $due<$now)||($stage && $stage<$now)) return 'overdue';
    if(($due && $due<=$now+max(1,absint($sla->warning_days))*DAY_IN_SECONDS)||($stage && $stage<=$now+max(1,absint($sla->warning_days))*DAY_IN_SECONDS)) return 'at_risk';
    return 'on_track';
}
function gd_client_portal_sla_remaining_label($date) {
    if(!$date) return __('No deadline','gd-client-portal'); $seconds=strtotime($date)-time(); if($seconds<0) return sprintf(__('%d days overdue','gd-client-portal'),max(1,ceil(abs($seconds)/DAY_IN_SECONDS)));
    if($seconds<DAY_IN_SECONDS) return __('Due today','gd-client-portal'); return sprintf(__('%d days left','gd-client-portal'),ceil($seconds/DAY_IN_SECONDS));
}
function gd_client_portal_sla_on_project_created($project) { if($project) gdcp_sla_service()->ensure($project); }
add_action('gd_client_portal_project_created','gd_client_portal_sla_on_project_created',20);
function gd_client_portal_sla_on_stage_changed($project_id,$new_stage) { gdcp_sla_service()->on_stage_changed($project_id); }
add_action('gd_client_portal_project_stage_changed','gd_client_portal_sla_on_stage_changed',20,2);
function gd_client_portal_get_sla_projects($mode='all',$limit=20) { return gdcp_sla_service()->projects($mode,$limit); }
function gd_client_portal_sla_set($project_id,$priority,$due_date,$stage_due_date='',$warning_days=2) { return gdcp_sla_service()->set($project_id,$priority,$due_date,$stage_due_date,$warning_days); }
function gd_client_portal_sla_escalate_due_projects() { return gdcp_sla_service()->escalate_due_projects(); }
add_action('gd_client_portal_sla_daily','gd_client_portal_sla_escalate_due_projects');
if(!wp_next_scheduled('gd_client_portal_sla_daily')) wp_schedule_event(time()+300,'daily','gd_client_portal_sla_daily');

function gd_client_portal_sla_ajax() {
    if(!gd_client_portal_verify_request()||!gd_client_portal_verify_nonce_request('gd_client_portal_operations')) wp_send_json_error(__('Invalid request.','gd-client-portal'));
    if(!(current_user_can('manage_options')||gd_client_portal_user_is_tenant_admin())) wp_send_json_error(__('You do not have permission.','gd-client-portal'));
    $id=absint($_POST['project_id']??0); $project=gd_client_portal_get_project_by_id($id); if(!$project||!gd_client_portal_verify_project_access($project)) wp_send_json_error(__('Project access denied.','gd-client-portal'));
    if(!gd_client_portal_sla_set($id,sanitize_key(wp_unslash($_POST['priority']??'normal')),sanitize_text_field(wp_unslash($_POST['due_date']??'')),sanitize_text_field(wp_unslash($_POST['stage_due_date']??'')),absint($_POST['warning_days']??2))) wp_send_json_error(__('Unable to save the SLA.','gd-client-portal'));
    wp_send_json_success(array('message'=>__('SLA updated.','gd-client-portal')));
}
add_action('wp_ajax_gd_client_portal_sla_action','gd_client_portal_sla_ajax');
