<?php
/**
 * Client approval and revision workflow.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_get_approval_table_name() { global $wpdb; return $wpdb->prefix . 'gd_project_approvals'; }

function gd_client_portal_activate_approval_table() {
    global $wpdb;
    $table = gd_client_portal_get_approval_table_name();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        project_id bigint(20) unsigned NOT NULL,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        version int(11) unsigned NOT NULL DEFAULT 1,
        status varchar(30) NOT NULL DEFAULT 'pending',
        requested_by bigint(20) unsigned NOT NULL,
        decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
        client_comment longtext NULL,
        internal_note longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_at datetime NULL,
        PRIMARY KEY (id),
        KEY project_id (project_id),
        KEY tenant_id (tenant_id),
        KEY status (status)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_get_active_approval($project_id) {
    global $wpdb;
    $table = gd_client_portal_get_approval_table_name();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE project_id=%d AND status='pending' ORDER BY id DESC LIMIT 1", $project_id));
}

function gd_client_portal_approval_actor_can_manage($project) {
    return $project && gd_client_portal_verify_project_access($project) && (current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin());
}

function gd_client_portal_approval_client_can_act($project) {
    return $project && gd_client_portal_verify_project_access($project) && !current_user_can('manage_options') && !gd_client_portal_user_is_tenant_admin();
}

function gd_client_portal_set_review_stage($project_id, $preferred = 'ready_for_review') {
    $project = gd_client_portal_get_project_by_id($project_id);
    if (!$project) return false;
    $workflow = gd_client_portal_get_workflow($project->service_type);
    $stage = in_array($preferred, $workflow, true) ? $preferred : (in_array('approval', $workflow, true) ? 'approval' : $project->current_stage);
    if ($stage === $project->current_stage) return true;
    return gd_client_portal_update_project_stage($project_id, $stage, gd_client_portal_auto_progress($project->service_type, $stage));
}

function gd_client_portal_submit_for_review_ajax() {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_nonce_request('gd_client_portal_approval')) wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
    $project_id=absint($_POST['project_id']??0);
    $service=function_exists('gdcp_service')?gdcp_service('approval'):null;
    if(!$service || !method_exists($service,'submit')) wp_send_json_error(__('Approval workflow is unavailable.','gd-client-portal'),500);
    $result=$service->submit($project_id);
    if(empty($result['ok'])) wp_send_json_error($result['message']??__('Unable to create the approval request.','gd-client-portal'),absint($result['code']??400));
    $project=$result['project']; $version=absint($result['version']);
    $owner=$project->user_id?gd_client_portal_cached_user($project->user_id):false;
    if($owner&&$owner->user_email) wp_mail($owner->user_email,sprintf(__('Approval requested: %s','gd-client-portal'),$project->title),sprintf(__('A new version of your project is ready for review: %s\n\nPlease open your project workspace to approve it or request a revision.','gd-client-portal'),$project->title));
    do_action('gd_client_portal_approval_requested',$project_id,$version);
    wp_send_json_success(array('message'=>__('Project submitted for client approval.','gd-client-portal'),'version'=>$version));
}

function gd_client_portal_decide_approval_ajax() {
    if (!gd_client_portal_verify_request() || !gd_client_portal_verify_nonce_request('gd_client_portal_approval')) wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
    $project_id=absint($_POST['project_id']??0); $decision=sanitize_key(wp_unslash($_POST['decision']??'')); $comment=sanitize_textarea_field(wp_unslash($_POST['comment']??''));
    $service=function_exists('gdcp_service')?gdcp_service('approval'):null;
    if(!$service || !method_exists($service,'decide')) wp_send_json_error(__('Approval workflow is unavailable.','gd-client-portal'),500);
    $result=$service->decide($project_id,$decision,$comment);
    if(empty($result['ok'])) wp_send_json_error($result['message']??__('Unable to save your decision.','gd-client-portal'),absint($result['code']??400));
    $project=$result['project']; $approval=$result['approval'];
    $subject=sprintf(__('Client decision: %s','gd-client-portal'),$project->title); $body=sprintf(__('The client has %s version %d of the project.','gd-client-portal'),$decision==='approved'?__('approved','gd-client-portal'):__('requested a revision for','gd-client-portal'),$approval->version);
    if($comment) $body.="\n\n".__('Client comment:','gd-client-portal')."\n".$comment;
    wp_mail(get_option('admin_email'),$subject,$body); do_action('gd_client_portal_approval_decided',$project_id,$decision,$comment);
    wp_send_json_success(array('message'=>$decision==='approved'?__('Project approved successfully.','gd-client-portal'):__('Revision request submitted.','gd-client-portal'),'status'=>$decision));
}

add_action('wp_ajax_gd_client_portal_submit_for_review','gd_client_portal_submit_for_review_ajax');
add_action('wp_ajax_gd_client_portal_decide_approval','gd_client_portal_decide_approval_ajax');

function gd_client_portal_render_approval_panel($project) {
    if (!$project || !gd_client_portal_verify_project_access($project)) return '';
    $approval = gd_client_portal_get_active_approval($project->id);
    global $wpdb;
    $history = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . gd_client_portal_get_approval_table_name() . " WHERE project_id=%d ORDER BY id DESC LIMIT 8", $project->id));
    $nonce = wp_create_nonce('gd_client_portal_approval');
    $can_manage = gd_client_portal_approval_actor_can_manage($project);
    $can_client = gd_client_portal_approval_client_can_act($project);
    ob_start();
    echo '<section class="gd-approval-panel gd-workspace-panel"><div class="gd-workspace-panel-head"><div><span class="gd-workspace-eyebrow">05</span><h3>'.esc_html__('Review & approval','gd-client-portal').'</h3></div>';
    if ($approval) echo '<span class="gd-approval-badge pending">'.esc_html__('Awaiting review','gd-client-portal').'</span>';
    echo '</div>';
    if ($approval) {
        echo '<div class="gd-approval-current"><div><strong>'.sprintf(esc_html__('Version %d is ready for review', 'gd-client-portal'), intval($approval->version)).'</strong><small>'.esc_html($approval->created_at).'</small></div></div>';
        if ($can_client) {
            echo '<form class="gd-approval-decision-form"><input type="hidden" name="project_id" value="'.intval($project->id).'"><input type="hidden" name="_wpnonce" value="'.esc_attr($nonce).'"><textarea name="comment" rows="4" placeholder="'.esc_attr__('Optional feedback or revision notes…','gd-client-portal').'"></textarea><div class="gd-approval-actions"><button type="button" class="gd-workspace-button gd-approval-action" data-decision="approved">'.esc_html__('Approve version','gd-client-portal').'</button><button type="button" class="gd-workspace-button gd-approval-action secondary" data-decision="revision_requested">'.esc_html__('Request revision','gd-client-portal').'</button></div><span class="gd-workspace-action-status" aria-live="polite"></span></form>';
        }
    } elseif ($can_manage) {
        echo '<div class="gd-approval-empty"><strong>'.esc_html__('Ready to request client approval?','gd-client-portal').'</strong><span>'.esc_html__('Create an approval checkpoint so the client can explicitly approve this version or send revision feedback.','gd-client-portal').'</span><button type="button" class="gd-workspace-button gd-submit-review" data-project-id="'.intval($project->id).'">'.esc_html__('Submit for client approval','gd-client-portal').'</button><span class="gd-workspace-action-status" aria-live="polite"></span></div>';
    } else {
        echo '<div class="gd-approval-empty"><span>'.esc_html__('No approval request is currently awaiting your review.','gd-client-portal').'</span></div>';
    }
    if ($history) {
        echo '<div class="gd-approval-history"><strong>'.esc_html__('Review history','gd-client-portal').'</strong>';
        foreach ($history as $item) {
            $label = $item->status === 'approved' ? __('Approved','gd-client-portal') : ($item->status === 'revision_requested' ? __('Revision requested','gd-client-portal') : __('Awaiting review','gd-client-portal'));
            echo '<div class="gd-approval-history-item"><span>'.esc_html($label).'</span><small>v'.intval($item->version).' · '.esc_html($item->decided_at ?: $item->created_at).'</small>';
            if (!empty($item->client_comment)) echo '<p>'.nl2br(esc_html($item->client_comment)).'</p>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</section>';
    return ob_get_clean();
}
