<?php
if (!defined('ABSPATH')) exit;

function gdcp_sla_boundary_regression() {
    $required=array('GDCP_SLA_Repository','GDCP_SLA_Service','gdcp_sla_repository','gdcp_sla_service');
    foreach($required as $name){ if((class_exists($name)||function_exists($name))===false) return new WP_Error('missing_boundary',$name.' missing'); }
    $repo=gdcp_sla_repository(); $service=gdcp_sla_service();
    if(!method_exists($repo,'find_by_project')||!method_exists($repo,'create')||!method_exists($repo,'update_by_project')||!method_exists($repo,'project_list')||!method_exists($repo,'due_projects')) return new WP_Error('repo_contract','SLA repository contract incomplete');
    foreach(array('get','ensure','set','on_stage_changed','projects','escalate_due_projects') as $method) if(!method_exists($service,$method)) return new WP_Error('service_contract','SLA service method missing: '.$method);
    return true;
}
if(defined('GDCP_RUN_SLA_BOUNDARY_REGRESSION') && GDCP_RUN_SLA_BOUNDARY_REGRESSION) { $r=gdcp_sla_boundary_regression(); if(is_wp_error($r)) wp_die(esc_html($r->get_error_message())); echo 'SLA boundary contract passed.'; }
