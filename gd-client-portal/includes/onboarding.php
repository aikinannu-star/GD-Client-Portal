<?php
/** Client onboarding & intake system. */
if (!defined('ABSPATH')) exit;

function gd_client_portal_onboarding_table() { global $wpdb; return $wpdb->prefix . 'gd_client_onboarding'; }
function gd_client_portal_onboarding_documents_table() { global $wpdb; return $wpdb->prefix . 'gd_client_onboarding_documents'; }

function gd_client_portal_activate_onboarding_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $t = gd_client_portal_onboarding_table();
    $d = gd_client_portal_onboarding_documents_table();
    dbDelta("CREATE TABLE $t (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status varchar(30) NOT NULL DEFAULT 'not_started',
        current_step tinyint(3) unsigned NOT NULL DEFAULT 1,
        company_name varchar(255) NOT NULL DEFAULT '',
        contact_name varchar(255) NOT NULL DEFAULT '',
        goals longtext NULL,
        timeline varchar(255) NOT NULL DEFAULT '',
        budget_range varchar(100) NOT NULL DEFAULT '',
        notes longtext NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime NULL,
        PRIMARY KEY (id), KEY tenant_id (tenant_id), KEY user_id (user_id), KEY project_id (project_id), KEY status (status)
    ) $charset;");
    dbDelta("CREATE TABLE $d (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        onboarding_id bigint(20) unsigned NOT NULL,
        project_id bigint(20) unsigned NOT NULL DEFAULT 0,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        title varchar(255) NOT NULL,
        file_url varchar(500) NOT NULL,
        mime_type varchar(150) NOT NULL DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY onboarding_id (onboarding_id), KEY project_id (project_id), KEY tenant_id (tenant_id)
    ) $charset;");
}

function gd_client_portal_onboarding_get($id) {
    $repo=function_exists('gdcp_repository')?gdcp_repository('onboarding'):null;
    return $repo ? $repo->find($id) : null;
}
function gd_client_portal_onboarding_get_for_project($project_id) {
    $repo=function_exists('gdcp_repository')?gdcp_repository('onboarding'):null;
    return $repo ? $repo->find_for_project($project_id) : null;
}
function gd_client_portal_onboarding_access($row) {
    if(!$row || !gd_client_portal_verify_request()) return false;
    if(gd_client_portal_is_platform_admin()) return true;
    $tenant=gd_client_portal_get_current_tenant_id();
    if($tenant<=0 || absint($row->tenant_id)!==$tenant) return false;
    if(gd_client_portal_user_is_tenant_admin()) return true;
    return absint($row->user_id)===gd_client_portal_cached_current_user_id();
}
function gd_client_portal_onboarding_create_for_project($project) {
    $service=function_exists('gdcp_service')?gdcp_service('onboarding'):null;
    return $service ? $service->create_for_project($project) : 0;
}
function gd_client_portal_onboarding_on_project_created($project) {
    gd_client_portal_onboarding_create_for_project($project);
}
add_action('gd_client_portal_project_created','gd_client_portal_onboarding_on_project_created',30,1);

function gd_client_portal_onboarding_on_claimed($project_id,$user_id) {
    $service=function_exists('gdcp_service')?gdcp_service('onboarding'):null;
    if($service) $service->claim($project_id,$user_id);
}
add_action('gd_client_portal_project_claimed','gd_client_portal_onboarding_on_claimed',30,2);

function gd_client_portal_onboarding_save_ajax() {
    gd_client_portal_ajax_guard('gd_client_portal_onboarding', '_wpnonce');
    $id=absint($_POST['onboarding_id']??0); $row=gd_client_portal_onboarding_get($id);
    if(!$row || !gd_client_portal_onboarding_access($row)) wp_send_json_error(__('You do not have permission to update this onboarding record.','gd-client-portal'));
    if(gd_client_portal_is_platform_admin() || gd_client_portal_user_is_tenant_admin()) { $uid=absint($row->user_id); } else $uid=gd_client_portal_cached_current_user_id();
    $step=max(1,min(6,absint($_POST['step']??$row->current_step)));
    $data=array(
        'company_name'=>sanitize_text_field(wp_unslash($_POST['company_name']??'')),
        'contact_name'=>sanitize_text_field(wp_unslash($_POST['contact_name']??'')),
        'goals'=>sanitize_textarea_field(wp_unslash($_POST['goals']??'')),
        'timeline'=>sanitize_text_field(wp_unslash($_POST['timeline']??'')),
        'budget_range'=>sanitize_text_field(wp_unslash($_POST['budget_range']??'')),
        'notes'=>sanitize_textarea_field(wp_unslash($_POST['notes']??'')),
        'current_step'=>$step,'status'=>$step>=6?'submitted':'in_progress','updated_at'=>current_time('mysql')
    );
    if($step>=6) $data['completed_at']=current_time('mysql');
    $service=function_exists('gdcp_service')?gdcp_service('onboarding'):null;
    $saved=$service ? $service->save($id,$data) : false;
    if(!$saved || empty($saved['ok'])) wp_send_json_error($saved['message']??__('Unable to save onboarding.','gd-client-portal'));
    if($step>=6) {
        $project=gd_client_portal_cached_project($row->project_id);
        if($project && $project->status==='awaiting_requirements') {
            $workflow=gd_client_portal_get_workflow($project->service_type); $idx=array_search($project->current_stage,$workflow,true); $next=($idx!==false && isset($workflow[$idx+1]))?$workflow[$idx+1]:($workflow[1]??$project->current_stage); $progress=gd_client_portal_auto_progress($project->service_type,$next); if($progress<=0 && $next!==$project->current_stage)$progress=10;
            if (function_exists('gdcp_service')) {
                $project_service = gdcp_service('project');
                if ($project_service && method_exists($project_service, 'update_stage')) {
                    $project_service->update_stage($project->id, $next, $progress);
                    $project_service->update($project->id, array('status'=>'processing'));
                }
            }
        }
        do_action('gd_client_portal_onboarding_completed',$id,$row->project_id,$uid);
    }
    wp_send_json_success(array('message'=>$step>=6?__('Onboarding submitted successfully.','gd-client-portal'):__('Onboarding saved.','gd-client-portal'),'status'=>$data['status'],'step'=>$step));
}
add_action('wp_ajax_gd_client_portal_onboarding_save','gd_client_portal_onboarding_save_ajax');

function gd_client_portal_onboarding_upload_ajax() {
    gd_client_portal_ajax_guard('gd_client_portal_onboarding', '_wpnonce');
    $id=absint($_POST['onboarding_id']??0); $row=gd_client_portal_onboarding_get($id);
    if(!$row || !gd_client_portal_onboarding_access($row)) wp_send_json_error(__('You do not have permission to upload here.','gd-client-portal'));
    if(empty($_FILES['document']['name'])) wp_send_json_error(__('Choose a document first.','gd-client-portal'));
    require_once ABSPATH.'wp-admin/includes/file.php';
    $mimes=array('jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','txt'=>'text/plain','zip'=>'application/zip');
    $validated=gd_client_portal_validate_upload($_FILES['document'],$mimes,10 * MB_IN_BYTES);
    if(is_wp_error($validated)) wp_send_json_error(array('message'=>$validated->get_error_message()),400);
    $upload=wp_handle_upload($_FILES['document'],array('test_form'=>false,'mimes'=>$mimes));
    if(!empty($upload['error'])) wp_send_json_error(sanitize_text_field($upload['error']));
    $service=function_exists('gdcp_service')?gdcp_service('onboarding'):null; $project=gd_client_portal_cached_project($row->project_id);
    $doc_id=$service ? $service->add_document($id,$project,array('name'=>sanitize_file_name($_FILES['document']['name']),'url'=>$upload['url'],'type'=>$upload['type'])) : 0;
    if(!$doc_id){ if(!empty($upload['file'])&&file_exists($upload['file'])) @unlink($upload['file']); wp_send_json_error(__('Unable to save uploaded document.','gd-client-portal')); }
    wp_send_json_success(__('Document uploaded.','gd-client-portal'));
}
add_action('wp_ajax_gd_client_portal_onboarding_upload','gd_client_portal_onboarding_upload_ajax');

function gd_client_portal_render_onboarding($atts=array()) {
    if(!gd_client_portal_verify_request()) return gd_client_portal_render_access_gate(__('Client Onboarding','gd-client-portal'),__('Please sign in to continue.','gd-client-portal'));
    $project_id=absint($_GET['project_id']??0); $row=$project_id?gd_client_portal_onboarding_get_for_project($project_id):null;
    if(!$row) { $projects=gd_client_portal_get_visible_projects(); foreach($projects as $p){$row=gd_client_portal_onboarding_get_for_project($p->id); if($row && in_array($row->status,array('not_started','in_progress','submitted'),true)) break;} }
    if(!$row || !gd_client_portal_onboarding_access($row)) return '<div class="gd-onboarding-empty"><strong>'.esc_html__('No onboarding available','gd-client-portal').'</strong><p>'.esc_html__('A service project will appear here after a qualifying purchase.','gd-client-portal').'</p></div>';
    $onboarding_service=function_exists('gdcp_service')?gdcp_service('onboarding'):null; $docs=$onboarding_service?$onboarding_service->documents_for($row->id):array();
    $project=gd_client_portal_cached_project($row->project_id); $step=max(1,min(6,absint($row->current_step))); $nonce=wp_create_nonce('gd_client_portal_onboarding');
    ob_start(); wp_enqueue_style('gd-client-portal-onboarding'); wp_enqueue_script('gd-client-portal-onboarding');
    echo '<div class="gd-onboarding" data-id="'.intval($row->id).'" data-nonce="'.esc_attr($nonce).'">';
    echo '<div class="gd-onboarding-hero"><div><span>GD CLIENT PORTAL · V2.9</span><h2>'.esc_html__('Client Onboarding','gd-client-portal').'</h2><p>'.esc_html__('Tell us what you need, provide the essentials, and get your project ready for delivery.','gd-client-portal').'</p></div><strong>'.intval($step).'/6</strong></div>';
    echo '<div class="gd-onboarding-progress"><span style="width:'.(($step/6)*100).'%"></span></div>';
    echo '<form id="gd-onboarding-form"><input type="hidden" name="onboarding_id" value="'.intval($row->id).'">';
    echo '<div class="gd-onboarding-grid">';
    echo '<label>Business / client name<input name="company_name" value="'.esc_attr($row->company_name).'" required></label><label>Primary contact<input name="contact_name" value="'.esc_attr($row->contact_name).'" required></label>';
    echo '<label class="full">Project goals & scope<textarea name="goals" rows="5" placeholder="What would you like us to achieve?">'.esc_textarea($row->goals).'</textarea></label>';
    echo '<label>Target timeline<input name="timeline" value="'.esc_attr($row->timeline).'" placeholder="e.g. 4–6 weeks"></label><label>Budget range<input name="budget_range" value="'.esc_attr($row->budget_range).'" placeholder="Optional"></label>';
    echo '<label class="full">Additional requirements / notes<textarea name="notes" rows="4">'.esc_textarea($row->notes).'</textarea></label></div>';
    echo '<div class="gd-onboarding-docs"><h3>'.esc_html__('Documents','gd-client-portal').'</h3><p>'.esc_html__('Upload briefs, brand files, references, spreadsheets or other project documents.','gd-client-portal').'</p><input type="file" id="gd-onboarding-document"><button type="button" class="gd-onboarding-button gd-onboarding-upload">'.esc_html__('Upload document','gd-client-portal').'</button><div class="gd-onboarding-doc-list">';
    foreach($docs as $doc) echo '<a href="'.esc_url(gd_client_portal_onboarding_document_download_url($doc)).'" target="_blank" rel="noopener">📎 '.esc_html($doc->title).'</a>';
    echo '</div></div>';
    echo '<div class="gd-onboarding-actions"><button type="button" class="gd-onboarding-button gd-onboarding-save" data-step="5">'.esc_html__('Save & continue','gd-client-portal').'</button><button type="button" class="gd-onboarding-button gd-onboarding-submit" data-step="6">'.esc_html__('Review & submit onboarding','gd-client-portal').'</button><span class="gd-onboarding-status" aria-live="polite"></span></div></form></div>';
    return ob_get_clean();
}
add_shortcode('gd_client_onboarding','gd_client_portal_render_onboarding');
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view')) gd_client_portal_register_dashboard_view('onboarding','gd_client_portal_render_onboarding');});

function gd_client_portal_register_onboarding_assets() {
    wp_register_style('gd-client-portal-onboarding',GD_CLIENT_PORTAL_URL.'assets/onboarding.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);
    wp_register_script('gd-client-portal-onboarding',GD_CLIENT_PORTAL_URL.'assets/onboarding.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true);
    wp_localize_script('gd-client-portal-onboarding','GDOnboarding',array('ajax_url'=>admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts','gd_client_portal_register_onboarding_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_onboarding_assets');

function gd_client_portal_render_onboarding_admin() {
    if(!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal'));
    global $wpdb; $rows=$wpdb->get_results('SELECT * FROM '.gd_client_portal_onboarding_table().' ORDER BY updated_at DESC LIMIT 100');
    if(!gd_client_portal_is_platform_admin()){ $tenant=gd_client_portal_get_current_tenant_id(); $rows=array_values(array_filter($rows,function($r)use($tenant){return absint($r->tenant_id)===$tenant;})); }
    if (function_exists('gdcp_admin_page_header')) {
        gdcp_admin_page_header(
            __('Client Onboarding','gd-client-portal'),
            __('Monitor client onboarding progress and identify the next workflow step.','gd-client-portal')
        );
    } else {
        echo '<div class="wrap"><h1>'.esc_html__('Client Onboarding','gd-client-portal').'</h1>';
    }

    if (!$rows) {
        if (function_exists('gdcp_admin_empty_state')) {
            gdcp_admin_empty_state(
                __('No onboarding records yet','gd-client-portal'),
                __('Onboarding records appear here after a client enters the service workflow.','gd-client-portal')
            );
        } else {
            echo '<p>'.esc_html__('No onboarding records found.','gd-client-portal').'</p>';
        }
    } else {
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Project</th><th>Client</th><th>Status</th><th>Step</th><th>Updated</th></tr></thead><tbody>';
        foreach($rows as $r){
            $u=gd_client_portal_cached_user($r->user_id);
            $p=gd_client_portal_cached_project($r->project_id);
            $status = ucwords(str_replace('_',' ',$r->status));
            $badge = function_exists('gdcp_admin_status_badge') ? gdcp_admin_status_badge($status, $r->status === 'completed' ? 'success' : 'neutral') : esc_html($status);
            echo '<tr><td>'.intval($r->id).'</td><td>'.($p?esc_html($p->title):'—').'</td><td>'.esc_html($u?$u->display_name:'Guest').'</td><td>'.$badge.'</td><td>'.intval($r->current_step).'/6</td><td>'.esc_html($r->updated_at).'</td></tr>';
        }
        echo '</tbody></table>';
    }

    if (function_exists('gdcp_admin_page_footer')) {
        gdcp_admin_page_footer();
    } else {
        echo '</div>';
    }
}
function gd_client_portal_register_onboarding_menu(){add_submenu_page('gd-client-portal',__('Client Onboarding','gd-client-portal'),__('Client Onboarding','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-onboarding','gd_client_portal_render_onboarding_admin');}
add_action('admin_menu','gd_client_portal_register_onboarding_menu',20);


function gd_client_portal_onboarding_document_download() {
    $id=absint($_GET['document_id']??0); $nonce=isset($_GET['_wpnonce'])?sanitize_text_field(wp_unslash($_GET['_wpnonce'])):'';
    if(!$id || !is_user_logged_in() || !wp_verify_nonce($nonce,'gd_client_portal_onboarding_document_download_'.$id)) wp_die(esc_html__('Invalid download request.','gd-client-portal'),'',array('response'=>403));
    global $wpdb; $doc=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.gd_client_portal_onboarding_documents_table().' WHERE id=%d',$id));
    $row=$doc?gd_client_portal_onboarding_get($doc->onboarding_id):null;
    if(!$doc || !$row || !gd_client_portal_onboarding_access($row)) wp_die(esc_html__('You do not have access to this document.','gd-client-portal'),'',array('response'=>403));
    gd_client_portal_stream_private_file($doc->file_url,$doc->title,$doc->mime_type);
}
add_action('admin_post_gd_client_portal_onboarding_document_download','gd_client_portal_onboarding_document_download');
function gd_client_portal_onboarding_document_download_url($doc){return wp_nonce_url(admin_url('admin-post.php?action=gd_client_portal_onboarding_document_download&document_id='.absint($doc->id)),'gd_client_portal_onboarding_document_download_'.absint($doc->id));}
