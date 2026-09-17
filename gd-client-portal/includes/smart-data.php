<?php
/** GD Client Portal v4.6 - Smart Variables & Data Mapping. */
if (!defined('ABSPATH')) exit;

function gd_client_portal_workflow_smart_variables() {
    return array(
        '{{project_id}}' => 'Project ID',
        '{{project.title}}' => 'Project title',
        '{{project.status}}' => 'Project status',
        '{{project.stage}}' => 'Current project stage',
        '{{project.progress}}' => 'Project progress',
        '{{project.service_type}}' => 'Service type',
        '{{project.tenant_id}}' => 'Project tenant ID',
        '{{client.name}}' => 'Client display name',
        '{{client.email}}' => 'Client email',
        '{{tenant.name}}' => 'Tenant name',
        '{{tenant.email}}' => 'Tenant email',
        '{{event}}' => 'Trigger event',
        '{{execution_id}}' => 'Automation execution ID',
        '{{now}}' => 'Current date/time',
        '{{date}}' => 'Current date',
    );
}

function gd_client_portal_workflow_smart_context($payload=array(),$project=false,$execution_id='') {
    $project = $project ?: (function_exists('gd_client_portal_automation_project') ? gd_client_portal_automation_project($payload) : false);
    $client = $project ? gd_client_portal_cached_user(absint($project->user_id)) : false;
    $tenant_id = $project ? absint($project->tenant_id) : absint($payload['tenant_id'] ?? 0);
    $tenant = false;
    if ($tenant_id && function_exists('gd_client_portal_get_tenant_by_id')) $tenant = gd_client_portal_get_tenant_by_id($tenant_id);
    $ctx = array(
        'project_id'=>absint($payload['project_id'] ?? ($project->id ?? 0)),
        'project'=>array(
            'id'=>absint($project->id ?? ($payload['project_id'] ?? 0)),
            'title'=>sanitize_text_field($project->title ?? ($payload['project_title'] ?? '')),
            'status'=>sanitize_text_field($project->status ?? ($payload['status'] ?? '')),
            'stage'=>sanitize_text_field($project->current_stage ?? ($payload['stage'] ?? '')),
            'progress'=>isset($project->progress) ? (int)$project->progress : (int)($payload['progress'] ?? 0),
            'service_type'=>sanitize_text_field($project->service_type ?? ($payload['service_type'] ?? '')),
            'tenant_id'=>$tenant_id,
        ),
        'client'=>array('name'=>$client ? sanitize_text_field($client->display_name) : sanitize_text_field($payload['client_name'] ?? ''),'email'=>$client ? sanitize_email($client->user_email) : sanitize_email($payload['client_email'] ?? '')),
        'tenant'=>array('name'=>is_object($tenant) ? sanitize_text_field($tenant->name ?? '') : (is_array($tenant) ? sanitize_text_field($tenant['name'] ?? '') : sanitize_text_field($payload['tenant_name'] ?? '')),'email'=>is_object($tenant) ? sanitize_email($tenant->email ?? '') : (is_array($tenant) ? sanitize_email($tenant['email'] ?? '') : sanitize_email($payload['tenant_email'] ?? ''))),
        'event'=>sanitize_key($payload['_automation_event'] ?? ''),
        'execution_id'=>sanitize_text_field($execution_id),
        'now'=>current_time('mysql'),
        'date'=>current_time('Y-m-d'),
    );
    foreach($payload as $k=>$v) if (!array_key_exists($k,$ctx) && is_scalar($v)) $ctx[sanitize_key($k)] = (string)$v;
    return $ctx;
}

function gd_client_portal_workflow_smart_replace($value,$payload=array(),$project=false,$execution_id='') {
    if (!is_string($value) || strpos($value,'{{')===false) return $value;
    $ctx=gd_client_portal_workflow_smart_context($payload,$project,$execution_id);
    return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',function($m) use ($ctx){
        $key=$m[1]; $v=function_exists('gd_client_portal_automation_value')?gd_client_portal_automation_value($ctx,$key):null;
        if ($v===null) return $m[0];
        return is_scalar($v) ? (string)$v : wp_json_encode($v);
    },$value);
}

function gd_client_portal_workflow_smart_action($action,$payload,$project=false,$execution_id='') {
    foreach(array('title','body','stage') as $key) if(isset($action[$key])) $action[$key]=gd_client_portal_workflow_smart_replace($action[$key],$payload,$project,$execution_id);
    foreach(array('assigned_to','due_days','delay_minutes','max_attempts') as $key) if(isset($action[$key]) && is_string($action[$key]) && strpos($action[$key],'{{')!==false) $action[$key]=absint(gd_client_portal_workflow_smart_replace($action[$key],$payload,$project,$execution_id));
    return $action;
}

function gd_client_portal_workflow_smart_admin() {
    if(!gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    echo '<div class="wrap"><h1>Smart Data &amp; Variables</h1><p>Use dynamic values inside workflow titles, messages, stages and task settings.</p><table class="widefat striped" style="max-width:900px"><thead><tr><th>Variable</th><th>Meaning</th></tr></thead><tbody>';
    foreach(gd_client_portal_workflow_smart_variables() as $v=>$label) echo '<tr><td><code>'.esc_html($v).'</code></td><td>'.esc_html($label).'</td></tr>';
    echo '</tbody></table><p><strong>Example:</strong> <code>Hello {{client.name}}, your {{project.title}} is now {{project.stage}} ({{project.progress}}%).</code></p><p>Unknown variables are left unchanged so a workflow can be safely reviewed before activation.</p></div>';
}
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Smart Data & Variables','Smart Data & Variables','gd_client_portal_access_admin','gd-client-portal-smart-data','gd_client_portal_workflow_smart_admin');},25);
add_shortcode('gd_workflow_smart_data','gd_client_portal_workflow_smart_admin');
