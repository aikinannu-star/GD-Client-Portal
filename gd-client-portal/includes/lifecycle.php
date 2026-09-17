<?php
/**
 * GD Client Portal v5.5 — Client Lifecycle Command Center.
 * Unifies onboarding, intake, commercial, delivery and experience milestones.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_lifecycle_status_label($status) {
    $map = array(
        'not_started'=>'Not started','in_progress'=>'In progress','submitted'=>'Submitted','completed'=>'Completed',
        'draft'=>'Draft','sent'=>'Awaiting client','accepted'=>'Accepted','declined'=>'Declined','expired'=>'Expired',
        'unpaid'=>'Unpaid','partially_paid'=>'Partially paid','paid'=>'Paid','overdue'=>'Overdue',
        'pending'=>'Pending','approved'=>'Approved','revision_requested'=>'Revision requested','published'=>'Published',
        'superseded'=>'Superseded','active'=>'Active','processing'=>'Processing','awaiting_requirements'=>'Awaiting requirements',
        'final_delivery'=>'Final delivery','revisions'=>'Revisions','resolved'=>'Resolved','closed'=>'Closed'
    );
    return $map[$status] ?? ucwords(str_replace('_',' ',(string)$status));
}

function gd_client_portal_lifecycle_stage($label, $status, $tone='neutral', $meta='') {
    return array('label'=>$label,'status'=>$status,'tone'=>$tone,'meta'=>$meta);
}

function gd_client_portal_lifecycle_project($project) {
    if (!$project) return array();
    $id = absint($project->id);
    $tenant = absint($project->tenant_id);
    $out = array('project'=>$project,'client'=>false,'stages'=>array(),'completion'=>0,'next'=>'');

    $out['client'] = $project->user_id ? gd_client_portal_cached_user($project->user_id) : false;
    $onboarding = function_exists('gd_client_portal_onboarding_get_for_project') ? gd_client_portal_onboarding_get_for_project($id) : null;
    $intake = function_exists('gd_client_portal_intake_get_submission_for_project') ? gd_client_portal_intake_get_submission_for_project($id) : null;
    $quote = function_exists('gdcp_billing_service') ? gdcp_billing_service()->repo_latest_quote_for_project($id) : null;
    $contract = function_exists('gd_client_portal_contract_latest') ? gd_client_portal_contract_latest($id) : null;
    $invoice = function_exists('gdcp_billing_service') ? gdcp_billing_service()->repo_latest_invoice_for_project($id) : null;
    $delivery = function_exists('gd_client_portal_get_latest_delivery') ? gd_client_portal_get_latest_delivery($id, true) : null;
    $approval = null;
    if(function_exists('gdcp_service')){ $as=gdcp_service('approval'); if($as && method_exists($as,'latest_for_project')) $approval=$as->latest_for_project($id); }
    if(!$approval && function_exists('gd_client_portal_get_active_approval')) $approval=gd_client_portal_get_active_approval($id);
    $feedback = function_exists('gdcp_feedback_service') ? gdcp_feedback_service()->latest_for_project($id) : null;

    $on_status = $onboarding ? (string)$onboarding->status : 'not_started';
    $intake_status = $intake ? (string)($intake->status ?? 'in_progress') : 'not_started';
    $quote_status = $quote ? (string)$quote->status : 'not_started';
    $contract_status = $contract ? (string)$contract->status : 'not_started';
    $invoice_status = $invoice ? (string)$invoice->status : 'not_started';
    if ($invoice && function_exists('gd_client_portal_payment_balance')) {
        $balance = gd_client_portal_payment_balance($invoice);
        if ($balance <= 0.009) $invoice_status = 'paid';
        elseif ((float)$invoice->amount_paid > 0) $invoice_status = 'partially_paid';
        elseif (!empty($invoice->due_date) && strtotime($invoice->due_date) < current_time('timestamp')) $invoice_status = 'overdue';
    }
    $delivery_status = $delivery ? (string)$delivery->status : 'not_started';
    $approval_status = $approval ? (string)$approval->status : 'not_started';
    $feedback_status = $feedback ? (string)$feedback->status : 'not_started';

    $stages = array(
        gd_client_portal_lifecycle_stage('Onboarding',$on_status,$onboarding && in_array($on_status,array('submitted','completed'),true)?'done':($onboarding?'active':'neutral')),
        gd_client_portal_lifecycle_stage('Service intake',$intake_status,$intake && in_array($intake_status,array('completed','submitted'),true)?'done':($intake?'active':'neutral')),
        gd_client_portal_lifecycle_stage('Quote',$quote_status,$quote_status==='accepted'?'done':($quote_status==='declined'||$quote_status==='expired'?'blocked':($quote?'active':'neutral')),$quote ? ($quote->quote_number ?? '') : ''),
        gd_client_portal_lifecycle_stage('Agreement',$contract_status,$contract_status==='accepted'?'done':($contract_status==='declined'||$contract_status==='expired'?'blocked':($contract?'active':'neutral')),$contract ? ($contract->contract_number ?? '') : ''),
        gd_client_portal_lifecycle_stage('Payment',$invoice_status,$invoice_status==='paid'?'done':($invoice_status==='overdue'?'blocked':($invoice?'active':'neutral')),$invoice ? ($invoice->invoice_number ?? '') : ''),
        gd_client_portal_lifecycle_stage('Delivery',$delivery_status,in_array($delivery_status,array('published','approved'),true)?'done':($delivery?'active':'neutral')),
        gd_client_portal_lifecycle_stage('Approval',$approval_status,$approval_status==='approved'?'done':($approval_status==='revision_requested'?'blocked':($approval?'active':'neutral'))),
        gd_client_portal_lifecycle_stage('Feedback',$feedback_status,$feedback && $feedback_status==='published'?'done':($feedback?'active':'neutral')),
    );
    $done=0;
    foreach($stages as $s) if($s['tone']==='done') $done++;
    $out['stages']=$stages;
    $out['completion']=(int)round(($done/count($stages))*100);

    if ($onboarding && !in_array($on_status,array('submitted','completed'),true)) $out['next']='Complete client onboarding';
    elseif ($intake && !in_array($intake_status,array('completed','submitted'),true)) $out['next']='Complete service intake';
    elseif (!$quote && in_array((string)$project->status,array('awaiting_requirements','processing'),true)) $out['next']='Prepare or send a quote';
    elseif ($quote && $quote_status==='sent') $out['next']='Await client quote decision';
    elseif ($quote && $quote_status==='accepted' && (!$contract || in_array($contract_status,array('draft','declined','expired'),true))) $out['next']='Create or send the agreement';
    elseif ($contract && $contract_status==='sent') $out['next']='Await client agreement decision';
    elseif ($invoice && in_array($invoice_status,array('unpaid','partially_paid','overdue'),true)) $out['next']='Follow up on invoice payment';
    elseif (!$delivery && !in_array((string)$project->status,array('completed','final_delivery'),true)) $out['next']='Publish the first deliverable';
    elseif ($approval && $approval_status==='pending') $out['next']='Await client approval';
    elseif ($approval && $approval_status==='revision_requested') $out['next']='Start the requested revision';
    elseif (!$feedback && in_array((string)$project->status,array('completed','final_delivery'),true)) $out['next']='Collect client feedback';
    else $out['next']='Continue project delivery';
    return $out;
}

function gd_client_portal_lifecycle_projects($limit=100, $status='', $tenant=0) {
    $projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects($limit) : array();
    $out=array();
    foreach ((array)$projects as $p) {
        if (!gd_client_portal_verify_project_access($p)) continue;
        if ($tenant && absint($p->tenant_id)!==absint($tenant)) continue;
        if ($status && (string)$p->status!==$status) continue;
        $out[]=gd_client_portal_lifecycle_project($p);
    }
    return $out;
}

function gd_client_portal_lifecycle_render($admin=true) {
    if (!gd_client_portal_verify_request()) return gd_client_portal_render_access_gate(__('Client Lifecycle','gd-client-portal'),__('Please sign in to view your project lifecycle.','gd-client-portal'));
    if ($admin && !gd_client_portal_user_can_access_admin()) wp_die(__('Access denied.','gd-client-portal'));
    $status=sanitize_key($_GET['lifecycle_status']??'');
    $tenant=absint($_GET['lifecycle_tenant']??0);
    if (!gd_client_portal_is_platform_admin()) $tenant=absint(gd_client_portal_get_current_tenant_id());
    $rows=gd_client_portal_lifecycle_projects(100,$status,$tenant);
    ob_start();
    echo '<div class="gdcp-lifecycle"><div class="gdcp-lifecycle-hero"><div><span>GD CLIENT PORTAL · V5.6</span><h1>Client Lifecycle Command Center</h1><p>Track every project from onboarding and intake through commercial approval, payment, delivery and client feedback.</p></div><div class="gdcp-lifecycle-summary"><strong>'.count($rows).'</strong><small>projects in view</small></div></div>';
    echo '<div class="gdcp-lifecycle-toolbar"><form method="get">';
    if($admin) echo '<input type="hidden" name="page" value="gd-client-portal-lifecycle">';
    else echo '<input type="hidden" name="gdcp_lifecycle" value="1">';
    echo '<label>Status <select name="lifecycle_status"><option value="">All statuses</option>';
    foreach(array('awaiting_requirements','processing','in_progress','completed','final_delivery') as $s) echo '<option value="'.esc_attr($s).'" '.selected($status,$s,false).'>'.esc_html(gd_client_portal_lifecycle_status_label($s)).'</option>';
    echo '</select></label>';
    if($admin && gd_client_portal_is_platform_admin()){ $tenants=gd_client_portal_get_all_tenants(); echo '<label>Tenant <select name="lifecycle_tenant"><option value="">All tenants</option>'; foreach((array)$tenants as $tid=>$t) echo '<option value="'.intval($tid).'" '.selected($tenant,absint($tid),false).'>'.esc_html($t['name']??('Tenant #'.$tid)).'</option>'; echo '</select></label>'; }
    echo '<button class="button button-primary" type="submit">Filter</button> <a class="button" href="'.esc_url($admin?admin_url('admin.php?page=gd-client-portal-lifecycle'):remove_query_arg(array('lifecycle_status','lifecycle_tenant'))).'">Reset</a></form></div>';
    foreach($rows as $row){$p=$row['project']; $client=$row['client']; $pct=$row['completion']; echo '<section class="gdcp-lifecycle-project"><div class="gdcp-lifecycle-project-head"><div><span class="gdcp-lifecycle-id">PROJECT #'.intval($p->id).'</span><h2>'.esc_html($p->title).'</h2><p>'.esc_html($client?$client->display_name:'Unassigned client').' · '.esc_html(gd_client_portal_lifecycle_status_label($p->status)).' · '.esc_html(ucwords(str_replace('_',' ',$p->current_stage))).'</p></div><div class="gdcp-lifecycle-progress"><strong>'.intval($pct).'%</strong><div><i style="width:'.intval($pct).'%"></i></div><small>Lifecycle completion</small></div></div><div class="gdcp-lifecycle-track">';
        foreach($row['stages'] as $i=>$s){ echo '<div class="gdcp-lifecycle-node '.esc_attr($s['tone']).'"><div class="gdcp-lifecycle-dot">'.($s['tone']==='done'?'✓':($i+1)).'</div><strong>'.esc_html($s['label']).'</strong><span>'.esc_html(gd_client_portal_lifecycle_status_label($s['status'])).'</span>'.($s['meta']?'<small>'.esc_html($s['meta']).'</small>':'').'</div>'; }
        echo '</div><div class="gdcp-lifecycle-next"><strong>Next recommended action</strong><span>'.esc_html($row['next']).'</span>'; $gap=function_exists('gd_client_portal_lifecycle_gap')?gd_client_portal_lifecycle_gap($row):array(); if(!empty($gap['action'])){ echo '<button type="button" class="button button-primary gdcp-lifecycle-action" data-project="'.intval($p->id).'" data-action="'.esc_attr($gap['action']).'">Run recommended action</button> '; } echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=gd-client-portal-command-center').'#project-'.intval($p->id)).'">Open Command Center</a></div></section>'; }
    if(!$rows) echo '<div class="gdcp-lifecycle-empty"><strong>No projects found.</strong><span>Adjust the filters or create a project to begin tracking its lifecycle.</span></div>';
    echo '</div>'; return ob_get_clean();
}

function gd_client_portal_lifecycle_admin_page(){ echo gd_client_portal_lifecycle_render(true); }
function gd_client_portal_lifecycle_shortcode(){ return gd_client_portal_lifecycle_render(false); }
add_shortcode('gd_client_lifecycle','gd_client_portal_lifecycle_shortcode');
add_shortcode('gd_client_lifecycle_center','gd_client_portal_lifecycle_shortcode');
add_action('admin_menu',function(){add_submenu_page('gd-client-portal','Client Lifecycle','Client Lifecycle','gd_client_portal_access_admin','gd-client-portal-lifecycle','gd_client_portal_lifecycle_admin_page');},20);

function gd_client_portal_lifecycle_assets(){
    wp_register_style('gd-client-portal-lifecycle',GD_CLIENT_PORTAL_URL.'assets/lifecycle.css',array('gd-client-portal'),GD_CLIENT_PORTAL_VERSION);
    if (is_admin() && isset($_GET['page']) && $_GET['page']==='gd-client-portal-lifecycle') wp_enqueue_style('gd-client-portal-lifecycle');
    if (!is_admin()) wp_enqueue_style('gd-client-portal-lifecycle');
}
add_action('admin_enqueue_scripts','gd_client_portal_lifecycle_assets');
add_action('wp_enqueue_scripts','gd_client_portal_lifecycle_assets');
