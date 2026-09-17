<?php
/**
 * GD Client Portal v2.2 - Assignment & Workload Management.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('gd_client_portal_assignments_table')) {
    function gd_client_portal_assignments_table() { global $wpdb; return $wpdb->prefix . 'gd_project_assignments'; }
}
if (!function_exists('gd_client_portal_activate_assignments_table')) {
    function gd_client_portal_activate_assignments_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = gd_client_portal_assignments_table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL,
            assignment_role varchar(30) NOT NULL DEFAULT 'member',
            assigned_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id,user_id),
            KEY tenant_id (tenant_id),
            KEY user_id (user_id),
            KEY project_id (project_id)
        ) {$charset};";
        dbDelta($sql);
    }
    // Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).
}

if (!function_exists('gd_client_portal_get_project_assignments')) {
    function gd_client_portal_get_project_assignments($project_id) {
        static $cache=array();
        $project_id = absint($project_id);
        if (!$project_id) return array();
        if(array_key_exists($project_id,$cache)) return $cache[$project_id];
        $cache[$project_id]=gdcp_assignment_service()->for_project($project_id);
        return $cache[$project_id];
    }
}
if (!function_exists('gd_client_portal_get_project_lead')) {
    function gd_client_portal_get_project_lead($project_id) {
        $rows = gd_client_portal_get_project_assignments($project_id);
        return $rows ? $rows[0] : false;
    }
}
if (!function_exists('gd_client_portal_get_assignable_users')) {
    function gd_client_portal_get_assignable_users($tenant_id) {
        $tenant_id = absint($tenant_id);
        if (!$tenant_id) return array();
        $users = gd_client_portal_get_users_for_tenant($tenant_id);
        return array_values(array_filter($users, function($u) {
            return !user_can($u, 'manage_options');
        }));
    }
}
if (!function_exists('gd_client_portal_set_project_assignment')) {
    function gd_client_portal_set_project_assignment($project_id, $user_id, $role = 'member') { return gdcp_assignment_service()->assign($project_id,$user_id,$role); }
}
if (!function_exists('gd_client_portal_remove_project_assignment')) {
    function gd_client_portal_remove_project_assignment($project_id, $user_id) { return gdcp_assignment_service()->remove($project_id,$user_id); }
}
if (!function_exists('gd_client_portal_get_user_workload')) {
    function gd_client_portal_get_user_workload($tenant_id, $limit = 100) {
        if (!$tenant_id) return array(); return gdcp_assignment_service()->workload($tenant_id,$limit);
    }
}

if (!function_exists('gd_client_portal_assignments_ajax')) {
    function gd_client_portal_assignments_ajax() {
        gd_client_portal_ajax_guard('gd_client_portal_assignments','_wpnonce');
        if (!(current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin())) wp_send_json_error(__('You do not have permission.','gd-client-portal'));
        $project_id=absint($_POST['project_id']??0); $user_id=absint($_POST['user_id']??0); $op=sanitize_key(wp_unslash($_POST['assignment_action']??''));
        $project=gd_client_portal_cached_project($project_id);
        if (!$project || !gd_client_portal_verify_project_access($project)) wp_send_json_error(__('Project access denied.','gd-client-portal'));
        if ($op==='assign') {
            $role=sanitize_key(wp_unslash($_POST['role']??'member'));
            if (!gd_client_portal_set_project_assignment($project_id,$user_id,$role)) wp_send_json_error(__('Unable to assign this team member.','gd-client-portal'));
            wp_send_json_success(array('message'=>__('Team member assigned.','gd-client-portal')));
        }
        if ($op==='remove') {
            if (!gd_client_portal_remove_project_assignment($project_id,$user_id)) wp_send_json_error(__('Unable to remove this assignment.','gd-client-portal'));
            wp_send_json_success(array('message'=>__('Assignment removed.','gd-client-portal')));
        }
        wp_send_json_error(__('Unknown assignment action.','gd-client-portal'));
    }
    add_action('wp_ajax_gd_client_portal_assignment_action','gd_client_portal_assignments_ajax');
}
