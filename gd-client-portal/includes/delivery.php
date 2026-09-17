<?php
/**
 * Delivery & Operations 2.0: versioned deliverables, publishing and final delivery.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_get_delivery_table_name() { global $wpdb; return $wpdb->prefix . 'gd_project_deliveries'; }

function gd_client_portal_activate_delivery_table() {
    global $wpdb;
    $table = gd_client_portal_get_delivery_table_name();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        project_id bigint(20) unsigned NOT NULL,
        tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
        version int(11) unsigned NOT NULL DEFAULT 1,
        title varchar(255) NOT NULL,
        description longtext NULL,
        file_url varchar(1000) NULL,
        status varchar(30) NOT NULL DEFAULT 'draft',
        uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
        approved_at datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY project_id (project_id), KEY tenant_id (tenant_id), KEY status (status)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
// Schema lifecycle is managed centrally by GDCP_Migration_Manager (v6.8+).

function gd_client_portal_delivery_can_manage($project) {
    return $project && gd_client_portal_verify_project_access($project) && (current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin());
}
function gd_client_portal_delivery_can_view($project) {
    return $project && gd_client_portal_verify_project_access($project);
}
function gd_client_portal_get_delivery_versions($project_id, $published_only = false) {
    return gdcp_delivery_service()->versions($project_id, $published_only);
}
function gd_client_portal_get_latest_delivery($project_id, $published_only = true) {
    return gdcp_delivery_service()->latest($project_id, $published_only);
}

function gd_client_portal_delivery_upload_ajax() {
    global $wpdb;
    gd_client_portal_ajax_guard('gd_client_portal_delivery', '_wpnonce');
    $project_id = absint($_POST['project_id'] ?? 0);
    $project = gd_client_portal_get_project_by_id($project_id);
    if (!gd_client_portal_delivery_can_manage($project)) wp_send_json_error(__("You do not have permission to manage this project's deliverables.", 'gd-client-portal'));
    $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
    $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
    if ($title === '') $title = __('Project deliverable', 'gd-client-portal');
    if (empty($_FILES['delivery_file']['name'])) wp_send_json_error(__('Please select a deliverable file.', 'gd-client-portal'));
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $allowed = array('pdf'=>'application/pdf','zip'=>'application/zip','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation','jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
    $validated = gd_client_portal_validate_upload($_FILES['delivery_file'], $allowed, 10 * MB_IN_BYTES);
    if (is_wp_error($validated)) wp_send_json_error(array('message' => $validated->get_error_message()), 400);
    $upload = wp_handle_upload($_FILES['delivery_file'], array('test_form'=>false,'mimes'=>$allowed));
    if (!empty($upload['error'])) wp_send_json_error(sanitize_text_field($upload['error']));
    $table = gd_client_portal_get_delivery_table_name();
    $project_table = gd_client_portal_get_project_table_name();
    // Serialize version allocation and publication per project. Without the project-row lock,
    // two simultaneous uploads can both calculate the same MAX(version)+1 and publish two
    // competing versions. Keep the delivery/project writes atomic as well.
    $wpdb->query('START TRANSACTION');
    $locked_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$project_table} WHERE id=%d FOR UPDATE", $project_id));
    if (!$locked_project || absint($locked_project->tenant_id) !== absint($project->tenant_id) || absint($locked_project->user_id) !== absint($project->user_id)) {
        $wpdb->query('ROLLBACK');
        wp_delete_file($upload['file']);
        wp_send_json_error(__('Project integrity check failed.', 'gd-client-portal'), 409);
    }
    $new_id = gdcp_delivery_service()->publish($locked_project, array('title'=>$title,'description'=>$description,'file_url'=>$upload['url'],'status'=>'published','uploaded_by'=>gd_client_portal_cached_current_user_id(),'created_at'=>current_time('mysql')));
    $version = $new_id ? absint(gdcp_delivery_service()->find($new_id)->version) : 0;
    if (!$new_id) {
        $wpdb->query('ROLLBACK');
        wp_delete_file($upload['file']);
        wp_send_json_error(__('Unable to save the deliverable.', 'gd-client-portal'));
    }
    $updated_project = false;
    if (function_exists('gdcp_service')) {
        $project_service = gdcp_service('project');
        if ($project_service && method_exists($project_service, 'update')) {
            $updated_project = $project_service->update($project_id, array('file_url'=>$upload['url'])) ? 1 : 0;
        }
    }
    if ($updated_project === false) {
        $wpdb->query('ROLLBACK');
        wp_delete_file($upload['file']);
        wp_send_json_error(__('Unable to update the project deliverable reference.', 'gd-client-portal'));
    }
    $project = $locked_project;
    $workflow = gd_client_portal_get_workflow($project->service_type);
    if (in_array('ready_for_review', $workflow, true) || in_array('approval', $workflow, true)) {
        $target = in_array('ready_for_review', $workflow, true) ? 'ready_for_review' : 'approval';
        if ($project->current_stage !== $target && !gd_client_portal_update_project_stage($project_id, $target, gd_client_portal_auto_progress($project->service_type, $target))) {
            $wpdb->query('ROLLBACK');
            wp_delete_file($upload['file']);
            wp_send_json_error(__('Unable to advance the project workflow after publishing the deliverable.', 'gd-client-portal'));
        }
    }
    $wpdb->query('COMMIT');
    do_action('gd_client_portal_delivery_published', $project_id, $version);
    wp_send_json_success(array('message'=>sprintf(__('Deliverable version %d published.', 'gd-client-portal'), $version),'version'=>$version));
}
add_action('wp_ajax_gd_client_portal_delivery_upload', 'gd_client_portal_delivery_upload_ajax');

function gd_client_portal_delivery_finalize_ajax() {
    global $wpdb;
    gd_client_portal_ajax_guard('gd_client_portal_delivery', '_wpnonce');
    $project_id = absint($_POST['project_id'] ?? 0);
    $project = gd_client_portal_get_project_by_id($project_id);
    if (!gd_client_portal_delivery_can_manage($project)) wp_send_json_error(__('You do not have permission to finalize this project.', 'gd-client-portal'));
    $table = gd_client_portal_get_delivery_table_name();
    $project_table = gd_client_portal_get_project_table_name();
    // Lock the project before selecting the current delivery so two finalize requests cannot
    // both close different versions or race a concurrent publish.
    $wpdb->query('START TRANSACTION');
    $locked_project = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$project_table} WHERE id=%d FOR UPDATE", $project_id));
    if (!$locked_project || !gd_client_portal_delivery_can_manage($locked_project)) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(__('You do not have permission to finalize this project.', 'gd-client-portal'), 403);
    }
    $delivery = gdcp_delivery_service()->latest($project_id, true);
    if (!$delivery) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(__('Publish a deliverable before finalizing the project.', 'gd-client-portal'));
    }
    if ($locked_project->status === 'completed') {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(__('This project has already been finalized.', 'gd-client-portal'), 409);
    }
    // Final closure must follow an explicit client approval for the exact current version.
    if (function_exists('gdcp_approval_service')) {
        $approved = gdcp_approval_service()->approved_for_project_version($project_id, absint($locked_project->tenant_id), absint($delivery->version), false);
        if (!$approved) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(__('The client must approve the current deliverable version before final delivery can be completed.', 'gd-client-portal'));
        }
    }
    $updated_delivery = gdcp_delivery_service()->approve($delivery->id);
    if ($updated_delivery !== 1) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(__('The deliverable changed while finalizing. Please try again.', 'gd-client-portal'), 409);
    }
    $project = $locked_project;
    $workflow = gd_client_portal_get_workflow($project->service_type);
    $target = in_array('final_delivery', $workflow, true) ? 'final_delivery' : (in_array('completed', $workflow, true) ? 'completed' : $project->current_stage);
    if ($target !== $project->current_stage) gd_client_portal_update_project_stage($project_id, $target, 100);
    $updated_project = false;
    if (function_exists('gdcp_service')) {
        $project_service = gdcp_service('project');
        if ($project_service && method_exists($project_service, 'update')) {
            $updated_project = $project_service->update($project_id, array('status'=>'completed','progress'=>100,'file_url'=>$delivery->file_url)) ? 1 : 0;
        }
    }
    if ($updated_project !== 1) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(__('The project changed while finalizing. Please try again.', 'gd-client-portal'), 409);
    }
    $wpdb->query('COMMIT');
    do_action('gd_client_portal_delivery_finalized', $project_id, $delivery->version);
    wp_send_json_success(array('message'=>__('Final delivery completed. The project is now closed.', 'gd-client-portal')));
}
add_action('wp_ajax_gd_client_portal_finalize_delivery', 'gd_client_portal_delivery_finalize_ajax');

function gd_client_portal_render_delivery_panel($project) {
    if (!gd_client_portal_delivery_can_view($project)) return '';
    $can_manage = gd_client_portal_delivery_can_manage($project);
    $items = gd_client_portal_get_delivery_versions($project->id, !$can_manage);
    $nonce = wp_create_nonce('gd_client_portal_delivery');
    ob_start();
    echo '<section class="gd-delivery-panel gd-workspace-panel"><div class="gd-workspace-panel-head"><div><span class="gd-workspace-eyebrow">03</span><h3>'.esc_html__('Deliverables & final delivery','gd-client-portal').'</h3></div><span>'.esc_html__('Version history','gd-client-portal').'</span></div>';
    if ($can_manage) {
        echo '<form class="gd-delivery-upload-form" enctype="multipart/form-data"><input type="hidden" name="project_id" value="'.intval($project->id).'"><input type="hidden" name="_wpnonce" value="'.esc_attr($nonce).'">';
        echo '<input type="text" name="title" placeholder="'.esc_attr__('Deliverable title','gd-client-portal').'" required><textarea name="description" rows="2" placeholder="'.esc_attr__('Optional release notes…','gd-client-portal').'"></textarea><input type="file" name="delivery_file" required><button type="submit" class="gd-workspace-button">'.esc_html__('Publish new version','gd-client-portal').'</button><span class="gd-workspace-action-status" aria-live="polite"></span></form>';
    }
    if ($items) {
        foreach ($items as $item) {
            $label = $item->status === 'approved' ? __('Final','gd-client-portal') : ($item->status === 'published' ? __('Published','gd-client-portal') : __('Superseded','gd-client-portal'));
            echo '<article class="gd-delivery-item"><div><strong>'.esc_html($item->title).'</strong><span>v'.intval($item->version).' · '.esc_html($label).' · '.esc_html($item->created_at).'</span>'; if ($item->description) echo '<p>'.nl2br(esc_html($item->description)).'</p>'; echo '</div><a class="gd-workspace-button secondary" href="'.esc_url(gd_client_portal_delivery_download_url($item)).'" target="_blank" rel="noopener noreferrer">'.esc_html__('Open file','gd-client-portal').'</a></article>';
        }
    } else echo '<div class="gd-workspace-empty compact"><strong>'.esc_html__('No published deliverables yet','gd-client-portal').'</strong><span>'.esc_html__('Your service team will publish project files here.','gd-client-portal').'</span></div>';
    if ($can_manage && gd_client_portal_get_latest_delivery($project->id, true) && $project->current_stage !== 'completed') echo '<div class="gd-delivery-finalize"><strong>'.esc_html__('Ready to close this project?','gd-client-portal').'</strong><span>'.esc_html__('Use this only after the client has approved the current version.','gd-client-portal').'</span><button type="button" class="gd-workspace-button gd-finalize-delivery" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($nonce).'">'.esc_html__('Mark final delivery complete','gd-client-portal').'</button><span class="gd-workspace-action-status" aria-live="polite"></span></div>';
    echo '</section>';
    return ob_get_clean();
}


function gd_client_portal_delivery_download() {
    $id=absint($_GET['delivery_id']??0); $nonce=isset($_GET['_wpnonce'])?sanitize_text_field(wp_unslash($_GET['_wpnonce'])):'';
    if(!$id || !is_user_logged_in() || !wp_verify_nonce($nonce,'gd_client_portal_delivery_download_'.$id)) wp_die(esc_html__('Invalid download request.','gd-client-portal'),'',array('response'=>403));
    $d=gdcp_delivery_service()->find($id);
    $project=$d?gd_client_portal_get_project_by_id($d->project_id):null;
    if(!$d || !gd_client_portal_delivery_can_view($project) || !gdcp_can('download','delivery',$id,array('project_id'=>absint($d->project_id)))) wp_die(esc_html__('You do not have access to this deliverable.','gd-client-portal'),'',array('response'=>403));
    gd_client_portal_stream_private_file($d->file_url, basename(parse_url($d->file_url,PHP_URL_PATH)));
}
add_action('admin_post_gd_client_portal_delivery_download','gd_client_portal_delivery_download');
function gd_client_portal_delivery_download_url($d){ return wp_nonce_url(admin_url('admin-post.php?action=gd_client_portal_delivery_download&delivery_id='.absint($d->id)),'gd_client_portal_delivery_download_'.absint($d->id)); }
