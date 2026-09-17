<?php
/** GD Client Portal v4.5 - Workflow Studio & Portability. */
if (!defined('ABSPATH')) exit;

function gd_client_portal_workflow_studio_sanitize_definition($data) {
    $events = function_exists('gd_client_portal_automation_events') ? gd_client_portal_automation_events() : array();
    $actions_allowed = function_exists('gd_client_portal_automation_actions') ? gd_client_portal_automation_actions() : array();
    $name = sanitize_text_field($data['name'] ?? 'Imported workflow');
    $event = sanitize_key($data['event_key'] ?? 'daily');
    if (!isset($events[$event])) $event = 'daily';
    $conditions = isset($data['conditions']) && is_array($data['conditions']) ? $data['conditions'] : array('logic'=>'AND','groups'=>array());
    $logic = strtoupper($conditions['logic'] ?? 'AND') === 'OR' ? 'OR' : 'AND';
    $groups = array();
    foreach ((array)($conditions['groups'] ?? array()) as $group) {
        $gl = strtoupper($group['logic'] ?? 'AND') === 'OR' ? 'OR' : 'AND';
        $conds = array();
        foreach ((array)($group['conditions'] ?? array()) as $c) {
            $field = sanitize_key($c['field'] ?? '');
            if (!$field) continue;
            $op = sanitize_key($c['operator'] ?? 'equals');
            if (!function_exists('gd_client_portal_automation_operators') || !isset(gd_client_portal_automation_operators()[$op])) $op = 'equals';
            $conds[] = array('field'=>$field,'operator'=>$op,'value'=>is_scalar($c['value'] ?? '') ? sanitize_text_field($c['value']) : '');
        }
        $groups[] = array('logic'=>$gl,'conditions'=>$conds);
    }
    $out_actions = array();
    foreach ((array)($data['actions'] ?? array()) as $a) {
        $type = sanitize_key($a['type'] ?? 'notify_team');
        if (!isset($actions_allowed[$type])) continue;
        $out_actions[] = array(
            'type'=>$type,
            'title'=>sanitize_text_field($a['title'] ?? ''),
            'body'=>sanitize_textarea_field($a['body'] ?? ''),
            'delay_minutes'=>min(43200,max(0,absint($a['delay_minutes'] ?? 0))),
            'stage'=>sanitize_key($a['stage'] ?? ''),
            'priority'=>in_array($a['priority'] ?? 'normal',array('low','normal','high','urgent'),true) ? $a['priority'] : 'normal',
            'assigned_to'=>absint($a['assigned_to'] ?? 0),
            'due_days'=>min(365,max(0,absint($a['due_days'] ?? 0))),
            'max_attempts'=>min(10,max(1,absint($a['max_attempts'] ?? 3)))
        );
    }
    return array('name'=>$name,'event_key'=>$event,'conditions'=>array('logic'=>$logic,'groups'=>$groups),'actions'=>$out_actions,'status'=>'paused');
}
function gd_client_portal_workflow_studio_export($rule) {
    return array('schema'=>'gdcp-workflow-v1','exported_at'=>current_time('mysql'),'workflow'=>array('name'=>$rule->name,'event_key'=>$rule->event_key,'conditions'=>gd_client_portal_automation_decode($rule->conditions,array()),'actions'=>gd_client_portal_automation_decode($rule->actions,array())));
}
function gd_client_portal_workflow_studio_admin() {
    if (!gd_client_portal_user_can_access_admin()) wp_die('Access denied.');
    $notice=''; $service=gdcp_automation_service();
    if (isset($_POST['gdcp_studio_bulk']) && check_admin_referer('gdcp_studio_bulk')) {
        $ids=array_map('absint',(array)($_POST['rule_ids']??array())); $mode=sanitize_key($_POST['bulk_mode']??'');
        if ($ids && in_array($mode,array('active','paused'),true)) { $changed=0; foreach($ids as $id){ if($service->set_rule_status($id,$mode)) $changed++; } $notice='<div class="notice notice-success"><p>'.intval($changed).' workflow(s) updated.</p></div>'; }
    }
    if (isset($_POST['gdcp_studio_import']) && check_admin_referer('gdcp_studio_import')) {
        $raw=wp_unslash($_POST['workflow_json']??''); $data=json_decode($raw,true);
        if (is_array($data) && isset($data['workflow']) && is_array($data['workflow'])) $data=$data['workflow'];
        if (is_array($data)) {
            $d=gd_client_portal_workflow_studio_sanitize_definition($data);
            if (!empty($d['actions'])) { $new_id=$service->import_rule($d,gd_client_portal_cached_current_user_id()); $notice=$new_id?'<div class="notice notice-success"><p>Workflow imported and paused for review.</p></div>':'<div class="notice notice-error"><p>Import could not be saved.</p></div>'; }
            else $notice='<div class="notice notice-error"><p>Import contains no valid actions.</p></div>';
        } else $notice='<div class="notice notice-error"><p>Invalid workflow JSON.</p></div>';
    }
    if (isset($_GET['duplicate_rule']) && check_admin_referer('gdcp_studio_duplicate_'.absint($_GET['duplicate_rule']))) {
        $r=gd_client_portal_automation_get_rule(absint($_GET['duplicate_rule']));
        if($r){$new_id=$service->duplicate_rule($r->id,gd_client_portal_cached_current_user_id());$notice=$new_id?'<div class="notice notice-success"><p>Workflow duplicated and paused.</p></div>':'<div class="notice notice-error"><p>Unable to duplicate workflow.</p></div>'; }
    }
    $rules=$service->list_rules();
    echo '<div class="wrap"><h1>Workflow Studio</h1><p>Manage, duplicate, import and export automation workflows safely. Imported and duplicated workflows start paused.</p><div class="notice notice-info inline"><p><strong>Smart variables:</strong> use <code>{{client.name}}</code>, <code>{{project.title}}</code>, <code>{{project.stage}}</code>, <code>{{project.progress}}</code>, <code>{{now}}</code> and more in action titles, messages and stages. <a href="' . esc_url(admin_url('admin.php?page=gd-client-portal-smart-data')) . '>View all variables</a>.</p></div>'.$notice;
    echo '<form method="post" style="margin:20px 0">'.wp_nonce_field('gdcp_studio_bulk','_wpnonce',true,false).'<input type="hidden" name="gdcp_studio_bulk" value="1"><table class="widefat striped"><thead><tr><th><input type="checkbox" id="gdcp-studio-all"></th><th>Workflow</th><th>Trigger</th><th>Status</th><th>Runs</th><th>Actions</th></tr></thead><tbody>';
    foreach((array)$rules as $r){$dup=wp_nonce_url(admin_url('admin.php?page=gd-client-portal-studio&duplicate_rule='.$r->id),'gdcp_studio_duplicate_'.$r->id);$exp=esc_url(add_query_arg(array('page'=>'gd-client-portal-studio','export_rule'=>$r->id),admin_url('admin.php')));echo '<tr><td><input type="checkbox" name="rule_ids[]" value="'.intval($r->id).'"></td><td><strong>'.esc_html($r->name).'</strong></td><td>'.esc_html(gd_client_portal_automation_events()[$r->event_key]??$r->event_key).'</td><td>'.esc_html($r->status).'</td><td>'.intval($r->run_count).'</td><td><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-automation&edit_rule='.$r->id)).'">Edit</a> <a class="button" href="'.esc_url($dup).'">Duplicate</a> <a class="button" href="'.esc_url($exp).'">Export JSON</a></td></tr>';} if(!$rules)echo '<tr><td colspan="6">No workflows found.</td></tr>'; echo '</tbody></table><p><select name="bulk_mode"><option value="">Bulk action…</option><option value="active">Activate selected</option><option value="paused">Pause selected</option></select> <button class="button">Apply</button></p></form>';
    echo '<hr><h2>Import workflow</h2><p>Paste a workflow export JSON below. The imported workflow is sanitized and created paused.</p><form method="post">'.wp_nonce_field('gdcp_studio_import','_wpnonce',true,false).'<input type="hidden" name="gdcp_studio_import" value="1"><textarea name="workflow_json" rows="12" style="width:100%;max-width:900px;font-family:monospace" placeholder="{&quot;schema&quot;:&quot;gdcp-workflow-v1&quot;,&quot;workflow&quot;:{...}}"></textarea><p><button class="button button-primary">Import workflow</button></p></form></div>';
}
add_action('admin_init',function(){if(!is_admin()||!isset($_GET['export_rule'])||!gd_client_portal_user_can_access_admin())return;$r=gd_client_portal_automation_get_rule(absint($_GET['export_rule']));if(!$r)wp_die('Workflow not found.');nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="gdcp-workflow-'.absint($r->id).'.json"');echo wp_json_encode(gd_client_portal_workflow_studio_export($r),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;});
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Workflow Studio','Workflow Studio','gd_client_portal_access_admin','gd-client-portal-studio','gd_client_portal_workflow_studio_admin');},24);
