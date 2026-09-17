<?php
/**
 * GD Client Portal v3.0 — Service-specific intake form builder.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_intake_forms_table(){ global $wpdb; return $wpdb->prefix.'gd_intake_forms'; }
function gd_client_portal_intake_submissions_table(){ global $wpdb; return $wpdb->prefix.'gd_intake_submissions'; }

function gd_client_portal_activate_intake_tables(){
    global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
    $f=gd_client_portal_intake_forms_table(); $s=gd_client_portal_intake_submissions_table();
    dbDelta("CREATE TABLE $f (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT, tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
      product_id bigint(20) unsigned NOT NULL DEFAULT 0, service_type varchar(100) NOT NULL DEFAULT '',
      title varchar(255) NOT NULL, description longtext NULL, status varchar(20) NOT NULL DEFAULT 'active',
      fields longtext NOT NULL, settings longtext NULL, created_by bigint(20) unsigned NOT NULL DEFAULT 0,
      created_at datetime DEFAULT CURRENT_TIMESTAMP, updated_at datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY(id), KEY tenant_id(tenant_id), KEY product_id(product_id), KEY service_type(service_type), KEY status(status)
    ) $c;");
    dbDelta("CREATE TABLE $s (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT, form_id bigint(20) unsigned NOT NULL, project_id bigint(20) unsigned NOT NULL DEFAULT 0,
      tenant_id bigint(20) unsigned NOT NULL DEFAULT 0, user_id bigint(20) unsigned NOT NULL DEFAULT 0,
      status varchar(30) NOT NULL DEFAULT 'in_progress', current_step tinyint(3) unsigned NOT NULL DEFAULT 1,
      responses longtext NULL, created_at datetime DEFAULT CURRENT_TIMESTAMP, updated_at datetime DEFAULT CURRENT_TIMESTAMP,
      submitted_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY project_form(project_id,form_id), KEY form_id(form_id), KEY tenant_id(tenant_id), KEY user_id(user_id), KEY status(status)
    ) $c;");
}

function gd_client_portal_intake_maybe_upgrade(){
    if(absint(get_option('gd_client_portal_intake_schema_version',0))<1){ gd_client_portal_activate_intake_tables(); update_option('gd_client_portal_intake_schema_version',1); }
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_intake_default_fields(){ return array(
  array('key'=>'contact_name','label'=>'Primary contact','type'=>'text','required'=>1,'step'=>1,'placeholder'=>'Your name'),
  array('key'=>'company_name','label'=>'Business / organization name','type'=>'text','required'=>0,'step'=>1,'placeholder'=>'Company or organization'),
  array('key'=>'project_goals','label'=>'What do you want this service to achieve?','type'=>'textarea','required'=>1,'step'=>2,'placeholder'=>'Describe the outcome you need'),
  array('key'=>'target_date','label'=>'Preferred completion date','type'=>'date','required'=>0,'step'=>3),
  array('key'=>'budget_range','label'=>'Budget range','type'=>'select','required'=>0,'step'=>3,'options'=>array('Not sure yet','Under $500','$500 – $1,000','$1,000 – $2,500','$2,500+')),
  array('key'=>'additional_notes','label'=>'Additional requirements or notes','type'=>'textarea','required'=>0,'step'=>4),
); }

function gd_client_portal_intake_decode_fields($form){ $x=json_decode((string)$form->fields,true); return is_array($x)?$x:array(); }
function gd_client_portal_intake_get_form($id){ return gdcp_intake_service()->form($id); }
function gd_client_portal_intake_get_submission($id){ return gdcp_intake_service()->submission($id); }
function gd_client_portal_intake_get_submission_for_project($project_id){ return gdcp_intake_service()->submission_for_project($project_id); }

function gd_client_portal_intake_form_access($form){
    if(!$form || !gd_client_portal_verify_request()) return false;
    if(gd_client_portal_is_platform_admin()) return true;
    $tenant=gd_client_portal_get_current_tenant_id(); return $tenant>0 && absint($form->tenant_id)===$tenant;
}
function gd_client_portal_intake_submission_access($sub){
    if(!$sub || !gd_client_portal_verify_request()) return false;
    if(gd_client_portal_is_platform_admin()) return true;
    $tenant=gd_client_portal_get_current_tenant_id(); if($tenant<=0 || absint($sub->tenant_id)!==$tenant) return false;
    if(gd_client_portal_user_is_tenant_admin()) return true;
    return absint($sub->user_id)===gd_client_portal_cached_current_user_id();
}

function gd_client_portal_intake_find_form($project){ return gdcp_intake_service()->find_form($project); }
function gd_client_portal_intake_create_for_project($project){
    if(!$project) return 0;
    $existing=gd_client_portal_intake_get_submission_for_project($project->id); if($existing) return absint($existing->id);
    $form=gd_client_portal_intake_find_form($project);
    if(!$form){
        $insert=gdcp_intake_service()->create_form(array('tenant_id'=>0,'product_id'=>0,'service_type'=>'','title'=>__('Default Service Intake','gd-client-portal'),'description'=>__('General intake for service projects without a dedicated form.','gd-client-portal'),'status'=>'active','fields'=>wp_json_encode(gd_client_portal_intake_default_fields()),'settings'=>wp_json_encode(array('steps'=>4)),'created_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
        if($insert) $form=gd_client_portal_intake_get_form($insert);
    }
    if(!$form) return 0;
    $id=gdcp_intake_service()->create_submission(array('form_id'=>$form->id,'project_id'=>$project->id,'tenant_id'=>$project->tenant_id,'user_id'=>$project->user_id,'status'=>'in_progress','current_step'=>1,'responses'=>wp_json_encode(array()),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
    return absint($id);
}
add_action('gd_client_portal_project_created',function($project){gd_client_portal_intake_create_for_project($project);},40,1);
add_action('gd_client_portal_project_claimed',function($project_id,$user_id){ $sub=gd_client_portal_intake_get_submission_for_project($project_id); if($sub) gdcp_intake_service()->update_submission($sub->id,array('user_id'=>absint($user_id),'updated_at'=>current_time('mysql'))); },40,2);

function gd_client_portal_intake_sanitize_value($field,$value){
    $type=$field['type']??'text';
    if($type==='checkbox'){ $vals=is_array($value)?$value:array($value); return array_values(array_filter(array_map('sanitize_text_field',$vals))); }
    if($type==='email') return sanitize_email($value);
    if($type==='url') return esc_url_raw($value);
    if($type==='textarea') return sanitize_textarea_field($value);
    return sanitize_text_field($value);
}
function gd_client_portal_intake_validate_responses($fields,$raw,$step=0){
    $out=array(); $errors=array(); $raw=is_array($raw)?$raw:array();
    foreach($fields as $field){ $key=sanitize_key($field['key']??''); if(!$key) continue; $fstep=absint($field['step']??1); if($step && $fstep>$step) continue; $value=$raw[$key]??''; if(($field['type']??'')==='checkbox' && !is_array($value)) $value=array($value); $empty=is_array($value)?empty(array_filter($value)):trim((string)$value)===''; if(!empty($field['required']) && $empty) $errors[$key]=sprintf(__('%s is required.','gd-client-portal'),$field['label']??$key); if(!$empty) $out[$key]=gd_client_portal_intake_sanitize_value($field,$value); else $out[$key]=($field['type']??'')==='checkbox'?array():''; }
    return array($out,$errors);
}

function gd_client_portal_intake_save_ajax(){
    gd_client_portal_ajax_guard('gd_client_portal_intake','_wpnonce');
    $id=absint($_POST['submission_id']??0); $sub=gd_client_portal_intake_get_submission($id); if(!$sub || !gd_client_portal_intake_submission_access($sub)) wp_send_json_error(__('You do not have permission to update this intake.','gd-client-portal'));
    $form=gd_client_portal_intake_get_form($sub->form_id); if(!$form) wp_send_json_error(__('Intake form not found.','gd-client-portal'));
    $raw=json_decode(wp_unslash($_POST['responses']??'{}'),true); if(!is_array($raw)) $raw=array(); $step=max(1,min(6,absint($_POST['step']??$sub->current_step))); [$clean,$errors]=gd_client_portal_intake_validate_responses(gd_client_portal_intake_decode_fields($form),$raw,$step); if($errors) wp_send_json_error(array('message'=>__('Please complete the required fields.','gd-client-portal'),'fields'=>$errors));
    $existing=json_decode((string)$sub->responses,true); $existing=is_array($existing)?$existing:array(); $merged=array_merge($existing,$clean); $status=$step>=6?'submitted':'in_progress'; $data=array('responses'=>wp_json_encode($merged),'current_step'=>$step,'status'=>$status,'updated_at'=>current_time('mysql')); if($step>=6) $data['submitted_at']=current_time('mysql'); $ok=gdcp_intake_service()->update_submission($id,$data); if($ok===false) wp_send_json_error(__('Unable to save intake.','gd-client-portal'));
    if($step>=6) gd_client_portal_intake_complete_project($sub->project_id,$id,gd_client_portal_cached_current_user_id());
    wp_send_json_success(array('message'=>$step>=6?__('Service intake submitted.','gd-client-portal'):__('Progress saved.','gd-client-portal'),'step'=>$step,'status'=>$status));
}
add_action('wp_ajax_gd_client_portal_intake_save','gd_client_portal_intake_save_ajax');

function gd_client_portal_intake_complete_project($project_id,$submission_id,$actor_id){
    $project=gd_client_portal_get_project_by_id($project_id); if(!$project) return;
    if($project->status==='awaiting_requirements'){
        $workflow=gd_client_portal_get_workflow($project->service_type); $idx=array_search($project->current_stage,$workflow,true); $next=($idx!==false && isset($workflow[$idx+1]))?$workflow[$idx+1]:($workflow[1]??$project->current_stage); $progress=gd_client_portal_auto_progress($project->service_type,$next); if($progress<=0 && $next!==$project->current_stage)$progress=10;
        $updated=false;
        if(function_exists('gdcp_service')){
            $service=gdcp_service('project');
            if($service && method_exists($service,'update_stage')){
                $updated=$service->update_stage($project->id,$next,$progress);
            }
        }
        if($updated && function_exists('gdcp_service')){
            $service=gdcp_service('project');
            if($service && method_exists($service,'update')) $service->update($project->id,array('status'=>'processing'));
        }
    }
    if(function_exists('gd_client_portal_notify_project')) gd_client_portal_notify_project($project,__('Service intake submitted','gd-client-portal'),sprintf(__('The intake for %s has been submitted.','gd-client-portal'),$project->title),'intake',$actor_id);
    if(function_exists('gd_client_portal_audit_event')) gd_client_portal_audit_event('intake_submitted',$project->id,sprintf(__('Service intake submitted for %s','gd-client-portal'),$project->title),__('The tailored service intake was submitted by a portal user.','gd-client-portal'),array('submission_id'=>$submission_id,'actor_id'=>$actor_id));
    do_action('gd_client_portal_intake_completed',$submission_id,$project_id,$actor_id);
}

function gd_client_portal_intake_render_field($field,$value){
    $key=sanitize_key($field['key']??''); $label=$field['label']??$key; $type=$field['type']??'text'; $req=!empty($field['required'])?' required':''; $ph=esc_attr($field['placeholder']??''); $html='<label class="gd-intake-field gd-intake-type-'.esc_attr($type).'" data-step="'.absint($field['step']??1).'">'; $html.='<span>'.esc_html($label).(!empty($field['required'])?' *':'').'</span>';
    if($type==='textarea') $html.='<textarea name="'.esc_attr($key).'" rows="5" placeholder="'.$ph.'"'.$req.'>'.esc_textarea($value).'</textarea>';
    elseif(in_array($type,array('select','radio'),true)){ $opts=is_array($field['options']??null)?$field['options']:array(); if($type==='select'){ $html.='<select name="'.esc_attr($key).'"'.$req.'><option value="">'.esc_html__('Select an option','gd-client-portal').'</option>'; foreach($opts as $o){$o=(string)$o;$html.='<option value="'.esc_attr($o).'"'.selected($value,$o,false).'>'.esc_html($o).'</option>';} $html.='</select>'; } else { foreach($opts as $o){$o=(string)$o;$html.='<label class="gd-intake-choice"><input type="radio" name="'.esc_attr($key).'" value="'.esc_attr($o).'"'.checked($value,$o,false).$req.'> '.esc_html($o).'</label>'; } } }
    elseif($type==='checkbox'){ $vals=is_array($value)?$value:array(); foreach((array)($field['options']??array()) as $o){$o=(string)$o;$html.='<label class="gd-intake-choice"><input type="checkbox" name="'.esc_attr($key).'[]" value="'.esc_attr($o).'"'.(in_array($o,$vals,true)?' checked':'').'> '.esc_html($o).'</label>'; } }
    elseif($type==='file'){ $html.='<input type="file" name="'.esc_attr($key).'" disabled><small>'.esc_html__('File fields use the project document uploader; add a document after saving this intake.','gd-client-portal').'</small>'; }
    else $html.='<input type="'.esc_attr(in_array($type,array('email','tel','url','number','date'),true)?$type:'text').'" name="'.esc_attr($key).'" value="'.esc_attr($value).'" placeholder="'.$ph.'"'.$req.'>';
    if(!empty($field['help'])) $html.='<small>'.esc_html($field['help']).'</small>'; return $html.'</label>';
}

function gd_client_portal_render_service_intake($atts=array()){
    if(!gd_client_portal_verify_request()) return gd_client_portal_render_access_gate(__('Service Intake','gd-client-portal'),__('Please sign in to continue.','gd-client-portal'));
    $project_id=absint($_GET['project_id']??0); $sub=$project_id?gd_client_portal_intake_get_submission_for_project($project_id):null;
    if(!$sub){ foreach(gd_client_portal_get_visible_projects() as $p){$sub=gd_client_portal_intake_get_submission_for_project($p->id); if($sub && $sub->status!=='submitted') break;} }
    if(!$sub || !gd_client_portal_intake_submission_access($sub)) return '<div class="gd-intake-empty"><strong>'.esc_html__('No service intake available','gd-client-portal').'</strong><p>'.esc_html__('A service-specific intake will appear after a qualifying service purchase.','gd-client-portal').'</p></div>';
    $form=gd_client_portal_intake_get_form($sub->form_id); if(!$form) return '';
    $fields=gd_client_portal_intake_decode_fields($form); $responses=json_decode((string)$sub->responses,true); $responses=is_array($responses)?$responses:array(); $steps=1; foreach($fields as $f)$steps=max($steps,absint($f['step']??1)); $steps=min(6,$steps); $current=max(1,min($steps,absint($sub->current_step))); wp_enqueue_style('gd-client-portal-intake'); wp_enqueue_script('gd-client-portal-intake'); $nonce=wp_create_nonce('gd_client_portal_intake');
    ob_start(); echo '<div class="gd-intake" data-id="'.intval($sub->id).'" data-steps="'.intval($steps).'" data-nonce="'.esc_attr($nonce).'">'; echo '<div class="gd-intake-hero"><div><span>GD CLIENT PORTAL · V3.0</span><h2>'.esc_html($form->title).'</h2><p>'.esc_html($form->description).'</p></div><b class="gd-intake-step-label">'.intval($current).'/'.intval($steps).'</b></div><div class="gd-intake-progress"><i style="width:'.(($current/$steps)*100).'%"></i></div><form class="gd-intake-form">';
    for($s=1;$s<=$steps;$s++){echo '<section class="gd-intake-step'.($s===$current?' is-active':''). '" data-step="'.intval($s).'">'; foreach($fields as $f){if(absint($f['step']??1)===$s) echo gd_client_portal_intake_render_field($f,$responses[sanitize_key($f['key']??'')]??'');} echo '<div class="gd-intake-actions">'.($s>1?'<button type="button" class="gd-intake-btn secondary" data-prev>Back</button>':'').($s<$steps?'<button type="button" class="gd-intake-btn" data-next>Save & continue</button>':'<button type="button" class="gd-intake-btn" data-submit>Submit service intake</button>').'<span class="gd-intake-status" aria-live="polite"></span></div></section>'; }
    echo '</form></div>'; return ob_get_clean();
}
add_shortcode('gd_service_intake','gd_client_portal_render_service_intake');
add_shortcode('gd_client_service_intake','gd_client_portal_render_service_intake');

function gd_client_portal_register_intake_assets(){ wp_register_style('gd-client-portal-intake',GD_CLIENT_PORTAL_URL.'assets/intake.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION); wp_register_script('gd-client-portal-intake',GD_CLIENT_PORTAL_URL.'assets/intake.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true); wp_register_script('gd-client-portal-intake-admin',GD_CLIENT_PORTAL_URL.'assets/intake-admin.js',array('jquery'),GD_CLIENT_PORTAL_VERSION,true); wp_localize_script('gd-client-portal-intake','GDIntake',array('ajax_url'=>admin_url('admin-ajax.php'))); }
add_action('wp_enqueue_scripts','gd_client_portal_register_intake_assets'); add_action('admin_enqueue_scripts','gd_client_portal_register_intake_assets');

function gd_client_portal_intake_admin_page(){
    if(!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal'));
    if(isset($_POST['gd_intake_action']) && wp_verify_nonce(wp_unslash($_POST['_wpnonce']??''),'gd_intake_admin')){
        $action=sanitize_key($_POST['gd_intake_action']); $id=absint($_POST['form_id']??0);
        if($action==='delete' && $id){
            $form=gd_client_portal_intake_get_form($id);
            if($form && gd_client_portal_intake_form_access($form)){gdcp_intake_service()->delete_form($id); echo '<div class="notice notice-success"><p>'.esc_html__('Intake form deleted.','gd-client-portal').'</p></div>';}
        } elseif($action==='save') {
            $tenant=gd_client_portal_is_platform_admin()?absint($_POST['tenant_id']??0):gd_client_portal_get_current_tenant_id();
            $title=sanitize_text_field(wp_unslash($_POST['title']??''));
            $description=sanitize_textarea_field(wp_unslash($_POST['description']??''));
            $product=absint($_POST['product_id']??0); $service=sanitize_key($_POST['service_type']??'');
            $status=in_array($_POST['status']??'active',array('active','draft'),true)?sanitize_key($_POST['status']):'active';
            $fields=json_decode(wp_unslash($_POST['fields_json']??'[]'),true); if(!is_array($fields))$fields=array(); $clean=array();
            foreach($fields as $f){
                $key=sanitize_key($f['key']??''); if(!$key)continue;
                $type=in_array($f['type']??'text',array('text','textarea','email','tel','url','number','date','select','radio','checkbox'),true)?$f['type']:'text';
                $clean[]=array('key'=>$key,'label'=>sanitize_text_field($f['label']??$key),'type'=>$type,'required'=>!empty($f['required'])?1:0,'step'=>max(1,min(6,absint($f['step']??1))),'placeholder'=>sanitize_text_field($f['placeholder']??''),'help'=>sanitize_text_field($f['help']??''),'options'=>array_values(array_filter(array_map('sanitize_text_field',(array)($f['options']??array())))));
            }
            $maxstep=1; foreach($clean as $f)$maxstep=max($maxstep,absint($f['step']));
            $data=array('tenant_id'=>$tenant,'product_id'=>$product,'service_type'=>$service,'title'=>$title?:__('Untitled Intake','gd-client-portal'),'description'=>$description,'status'=>$status,'fields'=>wp_json_encode($clean),'settings'=>wp_json_encode(array('steps'=>$maxstep)),'created_by'=>gd_client_portal_cached_current_user_id(),'updated_at'=>current_time('mysql'));
            $existing=$id?gd_client_portal_intake_get_form($id):null;
            if($existing && gd_client_portal_intake_form_access($existing)) gdcp_intake_service()->update_form($id,$data);
            else {$id=gdcp_intake_service()->create_form($data);}
            echo '<div class="notice notice-success"><p>'.esc_html__('Intake form saved.','gd-client-portal').'</p></div>';
        }
    }
    $edit=absint($_GET['edit']??0); $editing=$edit?gd_client_portal_intake_get_form($edit):null; if($editing && !gd_client_portal_intake_form_access($editing))$editing=null;
    $forms=gdcp_intake_service()->list_forms();
    $products=array();
    if(function_exists('wc_get_products')){
        $products=get_transient('gdcp_service_products_admin');
        if(!is_array($products)){
            $products=wc_get_products(array('status'=>'publish','limit'=>-1,'category'=>'service','orderby'=>'title','order'=>'ASC'));
            set_transient('gdcp_service_products_admin',is_array($products)?$products:array(),5*MINUTE_IN_SECONDS);
        }
    }
    $tenants=gd_client_portal_get_all_tenants();
    wp_enqueue_script('gd-client-portal-intake-admin');

    $header_actions=array(array(
        'url'=>admin_url('admin.php?page=gd-client-portal-intake'),
        'label'=>__('New intake form','gd-client-portal'),
        'primary'=>true,
    ));
    if(function_exists('gdcp_admin_page_header')){
        gdcp_admin_page_header(
            __('Service Intake Builder','gd-client-portal'),
            __('Build clear, service-specific client intake forms and map them to the right product or workflow type.','gd-client-portal'),
            $header_actions
        );
    } else {
        echo '<div class="wrap gd-intake-admin"><h1>'.esc_html__('Service Intake Builder','gd-client-portal').'</h1>';
    }

    echo '<div class="gdcp-intake-workflow-guide"><strong>'.esc_html__('Recommended setup:', 'gd-client-portal').'</strong> '
        .esc_html__('Choose the service → define the form → organize fields into steps → activate when ready.', 'gd-client-portal').'</div>';

    echo '<div class="gd-intake-admin-grid">';
    echo '<div class="gd-intake-editor">';
    echo '<div class="gdcp-intake-editor-heading"><div><h2>'.esc_html($editing?__('Edit intake form','gd-client-portal'):__('Create intake form','gd-client-portal')).'</h2><p class="description">'.esc_html__('Keep the form focused on information needed to start and deliver the service successfully.','gd-client-portal').'</p></div>';
    if($editing){
        $state=$editing->status==='active'?'success':'neutral';
        echo function_exists('gdcp_admin_status_badge') ? gdcp_admin_status_badge(ucfirst($editing->status),$state) : '<span>'.esc_html(ucfirst($editing->status)).'</span>';
    }
    echo '</div>';

    echo '<form method="post">';
    echo '<input type="hidden" name="gd_intake_action" value="save"><input type="hidden" name="form_id" value="'.intval($editing?$editing->id:0).'">';
    echo wp_nonce_field('gd_intake_admin','_wpnonce',true,false);

    echo '<div class="gdcp-intake-form-section"><h3>'.esc_html__('1. Form details','gd-client-portal').'</h3><table class="form-table">';
    echo '<tr><th>'.esc_html__('Title','gd-client-portal').'</th><td><input class="regular-text" name="title" required value="'.esc_attr($editing?$editing->title:'').'"><p class="description">'.esc_html__('Use a client-friendly title that identifies the service.','gd-client-portal').'</p></td></tr>';
    echo '<tr><th>'.esc_html__('Description','gd-client-portal').'</th><td><textarea class="large-text" name="description" rows="3">'.esc_textarea($editing?$editing->description:'').'</textarea></td></tr>';
    if(gd_client_portal_is_platform_admin()){
        echo '<tr><th>'.esc_html__('Tenant','gd-client-portal').'</th><td><select name="tenant_id"><option value="0">'.esc_html__('Global','gd-client-portal').'</option>';
        foreach($tenants as $tid=>$t) echo '<option value="'.intval($tid).'"'.selected($editing?$editing->tenant_id:0,$tid,false).'>'.esc_html($t['name']??('Tenant #'.$tid)).'</option>';
        echo '</select></td></tr>';
    }
    echo '</table></div>';

    echo '<div class="gdcp-intake-form-section"><h3>'.esc_html__('2. Service mapping','gd-client-portal').'</h3><p class="description">'.esc_html__('Product mapping is the most specific match, followed by service type, then the global default.','gd-client-portal').'</p><table class="form-table">';
    echo '<tr><th>'.esc_html__('WooCommerce service','gd-client-portal').'</th><td><select name="product_id"><option value="0">'.esc_html__('All service products','gd-client-portal').'</option>';
    foreach($products as $p) echo '<option value="'.intval($p->get_id()).'"'.selected($editing?$editing->product_id:0,$p->get_id(),false).'>'.esc_html($p->get_name()).' (#'.intval($p->get_id()).')</option>';
    echo '</select></td></tr>';
    echo '<tr><th>'.esc_html__('Service type','gd-client-portal').'</th><td><input class="regular-text" name="service_type" value="'.esc_attr($editing?$editing->service_type:'').'"><p class="description">'.esc_html__('Optional fallback mapping from the portal workflow type.','gd-client-portal').'</p></td></tr>';
    echo '<tr><th>'.esc_html__('Status','gd-client-portal').'</th><td><select name="status"><option value="active"'.selected($editing?$editing->status:'active','active',false).'>'.esc_html__('Active','gd-client-portal').'</option><option value="draft"'.selected($editing?$editing->status:'','draft',false).'>'.esc_html__('Draft','gd-client-portal').'</option></select><p class="description">'.esc_html__('Draft forms remain available for editing but are not selected as active matches.','gd-client-portal').'</p></td></tr>';
    echo '</table></div>';

    echo '<div class="gdcp-intake-form-section"><div class="gdcp-intake-fields-heading"><div><h3>'.esc_html__('3. Intake fields','gd-client-portal').'</h3><p class="description">'.esc_html__('Group fields into up to six steps to keep the client experience manageable.','gd-client-portal').'</p></div><button type="button" class="button" id="gd-intake-add-field">'.esc_html__('Add field','gd-client-portal').'</button></div><div id="gd-intake-fields"></div>';
    $field_json=$editing?$editing->fields:wp_json_encode(gd_client_portal_intake_default_fields());
    echo '<input type="hidden" id="gd-intake-fields-json" name="fields_json" value="'.esc_attr($field_json).'">';
    echo '</div>';

    echo '<div class="gdcp-intake-savebar"><span>'.esc_html__('Review the mapping and fields before saving.','gd-client-portal').'</span><button type="submit" class="button button-primary">'.esc_html__('Save intake form','gd-client-portal').'</button></div>';
    echo '</form></div>';

    echo '<aside class="gdcp-intake-sidebar">';
    echo '<div class="gdcp-intake-tips"><h2>'.esc_html__('Builder tips','gd-client-portal').'</h2><ol><li>'.esc_html__('Start with only essential questions.','gd-client-portal').'</li><li>'.esc_html__('Use steps to separate unrelated information.','gd-client-portal').'</li><li>'.esc_html__('Map one active form as specifically as possible.','gd-client-portal').'</li><li>'.esc_html__('Use Draft while a form is still being prepared.','gd-client-portal').'</li></ol></div>';
    echo '<div class="gdcp-intake-existing"><h2>'.esc_html__('Existing forms','gd-client-portal').'</h2>';
    if(!$forms){
        echo '<div class="gdcp-intake-mini-empty"><strong>'.esc_html__('No forms yet','gd-client-portal').'</strong><p>'.esc_html__('Create your first form using the editor.','gd-client-portal').'</p></div>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>'.esc_html__('Form','gd-client-portal').'</th><th>'.esc_html__('Mapping','gd-client-portal').'</th><th>'.esc_html__('Status','gd-client-portal').'</th><th>'.esc_html__('Actions','gd-client-portal').'</th></tr></thead><tbody>';
        foreach($forms as $f){
            $mapping=$f->product_id?'Product #'.intval($f->product_id):($f->service_type?'Type: '.esc_html($f->service_type):__('Global default','gd-client-portal'));
            $badge=function_exists('gdcp_admin_status_badge')?gdcp_admin_status_badge(ucfirst($f->status),$f->status==='active'?'success':'neutral'):esc_html(ucfirst($f->status));
            echo '<tr><td><strong>'.esc_html($f->title).'</strong><br><small>#'.intval($f->id).'</small></td><td>'.$mapping.'</td><td>'.$badge.'</td><td><a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-intake&edit='.intval($f->id))).'">'.esc_html__('Edit','gd-client-portal').'</a> <form style="display:inline" method="post" onsubmit="return confirm(&quot;Delete this intake form?&quot;);"><input type="hidden" name="gd_intake_action" value="delete"><input type="hidden" name="form_id" value="'.intval($f->id).'">'.wp_nonce_field('gd_intake_admin','_wpnonce',true,false).'<button class="button-link-delete" type="submit">'.esc_html__('Delete','gd-client-portal').'</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div></aside></div>';

    if(function_exists('gdcp_admin_page_footer')) gdcp_admin_page_footer(); else echo '</div>';
}
function gd_client_portal_register_intake_admin_menu(){add_submenu_page('gd-client-portal',__('Service Intake Builder','gd-client-portal'),__('Service Intake Builder','gd-client-portal'),'gd_client_portal_access_admin','gd-client-portal-intake','gd_client_portal_intake_admin_page');}
add_action('admin_menu','gd_client_portal_register_intake_admin_menu',20);
add_action('init',function(){if(function_exists('gd_client_portal_register_dashboard_view'))gd_client_portal_register_dashboard_view('service_intake','gd_client_portal_render_service_intake');});

add_action('save_post_product',function($post_id,$post,$update){
    if(wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    delete_transient('gdcp_service_products_admin');
},20,3);
add_action('deleted_post',function($post_id){
    if(get_post_type($post_id)==='product') delete_transient('gdcp_service_products_admin');
},20,1);
