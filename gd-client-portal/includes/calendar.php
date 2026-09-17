<?php
/**
 * GD Client Portal v2.4 - Client & Team Calendar / Project Timeline.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_calendar_table() { global $wpdb; return $wpdb->prefix . 'gd_project_calendar'; }
function gd_client_portal_activate_calendar_table() {
    global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table=gd_client_portal_calendar_table(); $charset=$wpdb->get_charset_collate();
    $sql="CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        project_id bigint(20) unsigned NOT NULL,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        event_type varchar(30) NOT NULL DEFAULT 'milestone',
        title varchar(190) NOT NULL,
        description text NULL,
        start_at datetime NOT NULL,
        end_at datetime NULL,
        status varchar(20) NOT NULL DEFAULT 'scheduled',
        created_by bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY project_id(project_id), KEY tenant_id(tenant_id), KEY user_id(user_id), KEY start_at(start_at), KEY event_type(event_type)
    ) {$charset};";
    dbDelta($sql);
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_calendar_scope_projects() {
    if (gd_client_portal_is_platform_admin()) return 'all';
    $tenant=absint(gd_client_portal_get_current_tenant_id());
    return $tenant>0 ? $tenant : 0;
}
function gd_client_portal_calendar_project_visible($project) {
    return $project && gd_client_portal_verify_project_access($project);
}
function gd_client_portal_calendar_format_type($type) {
    $map=array('project_start'=>'Project start','project_due'=>'Project deadline','stage_due'=>'Stage deadline','milestone'=>'Milestone','approval'=>'Approval','delivery'=>'Delivery','assignment'=>'Assignment');
    return $map[$type] ?? ucwords(str_replace('_',' ',$type));
}
function gd_client_portal_calendar_get_custom_events($start,$end,$project_id=0) {
    $rows=gdcp_calendar_service()->custom_events($start,$end,$project_id);
    return array_values(array_filter($rows,function($e){ $p=gd_client_portal_get_project_by_id($e->project_id); return gd_client_portal_calendar_project_visible($p); }));
}
function gd_client_portal_calendar_derived_events($start,$end,$project_id=0) {
    global $wpdb; $p_table=gd_client_portal_get_project_table_name(); $params=array();
    $where="WHERE p.status NOT IN ('cancelled','closed')";
    if ($project_id) { $where.=' AND p.id=%d'; $params[]=$project_id; }
    if (!gd_client_portal_is_platform_admin()) { $tenant=absint(gd_client_portal_get_current_tenant_id()); if(!$tenant) return array(); $where.=' AND p.tenant_id=%d'; $params[]=$tenant; }
    $projects=$wpdb->get_results($wpdb->prepare("SELECT p.* FROM {$p_table} p {$where} ORDER BY p.created_at ASC",$params)); $events=array();
    foreach($projects as $p){
        if(!gd_client_portal_calendar_project_visible($p)) continue;
        $sla=function_exists('gd_client_portal_get_sla')?gd_client_portal_get_sla($p->id):false;
        $add=function($type,$title,$date,$status='scheduled') use (&$events,$p,$start,$end){ if(!$date) return; $ts=strtotime($date); if(!$ts) return; $range_start=strtotime($start); $range_end=strtotime($end); if($ts<$range_start || $ts>$range_end) return; $events[]=array('id'=>'d'.$p->id.$type,'project_id'=>$p->id,'tenant_id'=>$p->tenant_id,'user_id'=>$p->user_id,'event_type'=>$type,'title'=>$title,'description'=>'','start_at'=>date('Y-m-d H:i:s',$ts),'end_at'=>null,'status'=>$status,'derived'=>true); };
        $add('project_start','Project start',$p->created_at,'completed');
        if($sla){ $add('project_due','Project deadline',$sla->due_date, strtotime($sla->due_date)<time()?'overdue':'scheduled'); $add('stage_due','Stage deadline: '.ucwords(str_replace('_',' ',$p->current_stage)),$sla->stage_due_date, strtotime($sla->stage_due_date)<time()?'overdue':'scheduled'); }
        $lead=function_exists('gd_client_portal_get_project_lead')?gd_client_portal_get_project_lead($p->id):false;
        if($lead){ $u=gd_client_portal_cached_user($lead->user_id); if($u) $events[]=array('id'=>'a'.$p->id,'project_id'=>$p->id,'tenant_id'=>$p->tenant_id,'user_id'=>$lead->user_id,'event_type'=>'assignment','title'=>'Lead: '.$u->display_name,'description'=>'','start_at'=>date('Y-m-d H:i:s',strtotime($lead->created_at)),'end_at'=>null,'status'=>'scheduled','derived'=>true); }
    }
    return $events;
}
function gd_client_portal_calendar_events($start,$end,$project_id=0) {
    return array_merge(gd_client_portal_calendar_derived_events($start,$end,$project_id),gd_client_portal_calendar_get_custom_events($start,$end,$project_id));
}
function gd_client_portal_calendar_add_event($project_id,$title,$start_at,$end_at='',$description='',$user_id=0) { return gdcp_calendar_service()->add($project_id,$title,$start_at,$end_at,$description,$user_id); }
function gd_client_portal_calendar_delete_event($event_id) { return gdcp_calendar_service()->delete($event_id); }
function gd_client_portal_calendar_ajax() {
    gd_client_portal_ajax_guard('gd_client_portal_calendar','_wpnonce');
    if(!(current_user_can('manage_options')||gd_client_portal_user_is_tenant_admin())) wp_send_json_error(__('You do not have permission.','gd-client-portal'));
    $op=sanitize_key(wp_unslash($_POST['calendar_action']??'')); $project_id=absint($_POST['project_id']??0);
    if($op==='add') { $id=gd_client_portal_calendar_add_event($project_id,sanitize_text_field(wp_unslash($_POST['title']??'')),sanitize_text_field(wp_unslash($_POST['start_at']??'')),sanitize_text_field(wp_unslash($_POST['end_at']??'')),sanitize_textarea_field(wp_unslash($_POST['description']??'')),absint($_POST['user_id']??0)); if(!$id) wp_send_json_error(__('Unable to schedule the milestone.','gd-client-portal')); wp_send_json_success(array('id'=>$id,'message'=>__('Milestone scheduled.','gd-client-portal'))); }
    if($op==='delete') { if(!gd_client_portal_calendar_delete_event(absint($_POST['event_id']??0))) wp_send_json_error(__('Unable to remove the milestone.','gd-client-portal')); wp_send_json_success(array('message'=>__('Milestone removed.','gd-client-portal'))); }
    wp_send_json_error(__('Unknown calendar action.','gd-client-portal'));
}
add_action('wp_ajax_gd_client_portal_calendar_action','gd_client_portal_calendar_ajax');

function gd_client_portal_render_calendar($atts=array()) {
    if(!gd_client_portal_verify_request() || !gd_client_portal_verify_tenant_access()) return gd_client_portal_render_access_gate();
    wp_enqueue_style('gd-client-portal-calendar'); wp_enqueue_script('gd-client-portal-calendar');
    $month=sanitize_text_field($_GET['calendar_month']??current_time('Y-m')); if(!preg_match('/^\d{4}-\d{2}$/',$month)) $month=current_time('Y-m');
    $first=strtotime($month.'-01 00:00:00'); if(!$first) $first=strtotime(current_time('Y-m').'-01 00:00:00'); $days=(int)gmdate('t',$first); $first_dow=(int)gmdate('w',$first); $start=gmdate('Y-m-d H:i:s',$first); $end=gmdate('Y-m-d H:i:s',strtotime('+1 day',strtotime($month.'-'.$days.' 23:59:59')));
    $project_id=absint($_GET['project_id']??0); if($project_id){$p=gd_client_portal_get_project_by_id($project_id); if(!$p||!gd_client_portal_calendar_project_visible($p)) $project_id=0;}
    $events=gd_client_portal_calendar_events($start,$end,$project_id); $by_day=array(); foreach($events as $e){$d=wp_date('Y-m-d',strtotime($e['start_at']??$e->start_at));$by_day[$d][]=$e;}
    $prev=gmdate('Y-m',strtotime('-1 month',$first)); $next=gmdate('Y-m',strtotime('+1 month',$first));
    ob_start(); echo '<div class="gd-calendar-app" data-nonce="'.esc_attr(wp_create_nonce('gd_client_portal_calendar')).'">';
    echo '<header class="gd-calendar-hero"><div><span class="gd-calendar-eyebrow">PROJECT SCHEDULE</span><h2>'.esc_html(wp_date('F Y',$first)).'</h2><p>'.esc_html__('Deadlines, milestones, assignments and delivery moments in one operational timeline.','gd-client-portal').'</p></div><div class="gd-calendar-nav"><a href="'.esc_url(add_query_arg('calendar_month',$prev)).'">‹</a><a class="today" href="'.esc_url(remove_query_arg('calendar_month')).'">'.esc_html__('Today','gd-client-portal').'</a><a href="'.esc_url(add_query_arg('calendar_month',$next)).'">›</a></div></header>';
    echo '<div class="gd-calendar-layout"><section class="gd-calendar-grid"><div class="gd-calendar-weekdays">'; foreach(array('Sun','Mon','Tue','Wed','Thu','Fri','Sat') as $wd) echo '<span>'.esc_html($wd).'</span>'; echo '</div><div class="gd-calendar-days">';
    for($i=0;$i<$first_dow;$i++) echo '<div class="gd-calendar-day is-empty"></div>'; for($day=1;$day<=$days;$day++){ $date=gmdate('Y-m-d',$first+$i=0); $date=sprintf('%s-%02d',$month,$day); $is_today=$date===current_time('Y-m-d'); echo '<div class="gd-calendar-day'.($is_today?' is-today':'').'"><b>'.intval($day).'</b>'; if(!empty($by_day[$date])) foreach(array_slice($by_day[$date],0,4) as $e){$title=is_array($e)?$e['title']:$e->title;$type=is_array($e)?$e['event_type']:$e->event_type;$status=is_array($e)?$e['status']:$e->status;$pid=is_array($e)?$e['project_id']:$e->project_id; echo '<a class="gd-calendar-event type-'.esc_attr($type).' status-'.esc_attr($status).'" href="'.esc_url(add_query_arg(array('project_id'=>$pid,'calendar_month'=>$month))).'"><span>'.esc_html($title).'</span></a>'; } if(count($by_day[$date]??array())>4) echo '<small>+'.(count($by_day[$date])-4).' more</small>'; echo '</div>'; } echo '</div></section>';
    echo '<aside class="gd-calendar-timeline"><div class="gd-calendar-panel-head"><span>UP NEXT</span><h3>'.esc_html__('Project timeline','gd-client-portal').'</h3></div>';
    $flat=$events; usort($flat,function($a,$b){$aa=is_array($a)?$a['start_at']:$a->start_at;$bb=is_array($b)?$b['start_at']:$b->start_at;return strcmp($aa,$bb);});
    $timeline_project_ids=array(); foreach($flat as $event){$timeline_project_ids[]=is_array($event)?$event['project_id']:$event->project_id;}
    $timeline_projects=function_exists('gd_client_portal_cached_projects')?gd_client_portal_cached_projects($timeline_project_ids):array();
    if($flat){ foreach($flat as $e){ $pid=is_array($e)?$e['project_id']:$e->project_id; $title=is_array($e)?$e['title']:$e->title; $type=is_array($e)?$e['event_type']:$e->event_type; $status=is_array($e)?$e['status']:$e->status; $dt=is_array($e)?$e['start_at']:$e->start_at; $p=$timeline_projects[absint($pid)]??null; echo '<article class="gd-calendar-timeline-item status-'.esc_attr($status).'" data-project-id="'.intval($pid).'"><i></i><div><small>'.esc_html(gd_client_portal_calendar_format_type($type)).' · '.esc_html(wp_date('M j, Y · g:i a',strtotime($dt))).'</small><strong>'.esc_html($title).'</strong><a href="'.esc_url(add_query_arg(array('project_id'=>$pid,'calendar_month'=>$month))).'">'.esc_html($p?$p->title:__('Open project','gd-client-portal')).' →</a></div></article>'; } } else echo '<div class="gd-calendar-empty">'.esc_html__('No scheduled events in this period.','gd-client-portal').'</div>';
    echo '</aside></div>';
    if(current_user_can('manage_options')||gd_client_portal_user_is_tenant_admin()) echo '<section class="gd-calendar-add"><div><span>OPERATIONS</span><h3>'.esc_html__('Schedule a milestone','gd-client-portal').'</h3><p>'.esc_html__('Add a team-facing milestone directly to a project timeline.','gd-client-portal').'</p></div><form class="gd-calendar-form"><input type="hidden" name="action" value="gd_client_portal_calendar_action"><input type="hidden" name="calendar_action" value="add"><select name="project_id" required><option value="">'.esc_html__('Select project…','gd-client-portal').'</option>'; $projects=gd_client_portal_get_visible_projects(); foreach($projects as $p) echo '<option value="'.intval($p->id).'"'.selected($project_id,$p->id,false).'>'.esc_html($p->title).'</option>'; echo '</select><input name="title" required placeholder="Milestone title"><input type="datetime-local" name="start_at" required><input type="datetime-local" name="end_at"><textarea name="description" placeholder="Notes (optional)"></textarea><button type="submit">'.esc_html__('Add milestone','gd-client-portal').'</button><span class="gd-calendar-form-status"></span></form></section>';
    echo '</div>'; return ob_get_clean();
}
function gd_client_portal_register_calendar_assets(){ wp_register_style('gd-client-portal-calendar',GD_CLIENT_PORTAL_URL.'assets/calendar.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION); wp_register_script('gd-client-portal-calendar',GD_CLIENT_PORTAL_URL.'assets/calendar.js',array(),GD_CLIENT_PORTAL_VERSION,true); }
add_action('wp_enqueue_scripts','gd_client_portal_register_calendar_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_calendar_assets');
function gd_client_portal_register_calendar_menu(){ add_submenu_page('gd-client-portal',__('Calendar & Timeline','gd-client-portal'),__('Calendar & Timeline','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-calendar','gd_client_portal_render_calendar_admin'); }
add_action('admin_menu','gd_client_portal_register_calendar_menu',20);
function gd_client_portal_render_calendar_admin(){ if(!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal')); echo '<div class="wrap gd-calendar-admin-wrap">'.gd_client_portal_render_calendar().'</div>'; }
add_shortcode('gd_project_calendar','gd_client_portal_render_calendar');
add_action('init',function(){ if(function_exists('gd_client_portal_register_dashboard_view')) gd_client_portal_register_dashboard_view('calendar','gd_client_portal_render_calendar'); });
