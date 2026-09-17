<?php
/**
 * Service project dashboard, deliverables, workflow, and modal shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_get_workflow')) {
    function gd_client_portal_get_workflow($service_type)
    {
        $workflows = array(
            'software_engineering' => array('intake', 'requirement_analysis', 'system_design', 'development', 'testing', 'deployment', 'completed'),
            'fashion_design' => array('intake', 'concept_development', 'mood_board', 'fabric_selection', 'sketching', 'production', 'final_delivery'),
            'graphic_design' => array('intake', 'brief_analysis', 'concepts', 'revisions', 'final_delivery'),
            'real_estate' => array('intake', 'market_analysis', 'listing', 'marketing', 'closing'),
            'architecture_building' => array('intake', 'site_analysis', 'concept_design', 'drawings', '3d_visualization', 'approval'),
            'trading_finance_education' => array('assessment', 'learning', 'strategy', 'practice', 'review'),
            'marketing' => array('intake', 'brand_audit', 'strategy', 'content_creation', 'campaign', 'optimization'),
            'commercial_services' => array('intake', 'proposal', 'execution', 'reporting'),
        );

        return $workflows[$service_type] ?? array('intake');
    }
}

if (!function_exists('gd_client_portal_get_service_type')) {
    function gd_client_portal_get_service_type($product_id)
    {
        $product_terms = get_the_terms($product_id, 'product_cat');

        if (!is_array($product_terms) || empty($product_terms)) {
            return 'commercial_services';
        }

        foreach ($product_terms as $term) {
            if (strtolower($term->slug) === 'service') {
                return 'commercial_services';
            }

            if (in_array(strtolower($term->slug), array('software-engineering', 'fashion-design', 'graphic-design', 'real-estate', 'architecture-building', 'trading-finance-education', 'marketing'), true)) {
                return str_replace('-', '_', $term->slug);
            }
        }

        return 'commercial_services';
    }
}

if (!function_exists('gd_client_portal_order_contains_service')) {
    function gd_client_portal_order_contains_service($order)
    {
        if (!is_a($order, 'WC_Order')) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $is_service = get_post_meta($product_id, 'is_service_product', true);

            if ($is_service === '1' || has_term('service', 'product_cat', $product_id)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('gd_client_portal_service_order_thankyou_prompt')) {
    function gd_client_portal_service_order_thankyou_prompt($order_id)
    {
        if (!gd_client_portal_is_woocommerce_active()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || !gd_client_portal_order_contains_service($order)) {
            return;
        }

        // Check admin setting
        $enabled = get_option('gd_client_portal_woo_redirect_after_service', '0');

        if ($enabled === '1') {
            // If user is logged in, send to configured dashboard page (if provided)
            if (is_user_logged_in()) {
                $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
                if (!empty($dash)) {
                    wp_safe_redirect($dash);
                    exit;
                }
                // otherwise let the regular thankyou flow continue
                return;
            }

            // Guest user: redirect to configured auth page if available, pass order context
            $auth = gd_client_portal_get_redirect_page_url('gd_client_portal_auth_page_id', 'gd_client_portal_auth_page_url');
            if (!empty($auth)) {
                $redirect = add_query_arg(array('gd_post_purchase' => '1', 'order_id' => intval($order_id)), $auth);
                wp_safe_redirect($redirect);
                exit;
            }
        }

        // Fallback: show informational message linking to My Account
        $my_account_url = wc_get_page_permalink('myaccount');
        if (empty($my_account_url)) {
            return;
        }

        echo '<div class="woocommerce-info">' . esc_html__('Thank you for your service purchase. Please log in or register on the My Account page to access your Client Dashboard.', 'gd-client-portal') . ' <a href="' . esc_url($my_account_url) . '">' . esc_html__('Go to My Account', 'gd-client-portal') . '</a></div>';
    }
}

if (!function_exists('gd_client_portal_get_project_table_name')) {
    function gd_client_portal_get_project_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'gd_projects';
    }
}

if (!function_exists('gd_client_portal_get_project_by_id')) {
    /** Legacy compatibility wrapper; canonical reads live in GDCP_Project_Repository. */
    function gd_client_portal_get_project_by_id($project_id)
    {
        $repo = function_exists('gdcp_repository') ? gdcp_repository('project') : null;
        return $repo && method_exists($repo, 'find') ? $repo->find($project_id) : null;
    }
}

if (!function_exists('gd_client_portal_get_project_by_order_id')) {
    /** Legacy compatibility wrapper; canonical order lookup lives in the project repository/service. */
    function gd_client_portal_get_project_by_order_id($order_id)
    {
        $service = function_exists('gdcp_service') ? gdcp_service('project') : null;
        return $service && method_exists($service, 'get_by_order_id') ? $service->get_by_order_id($order_id) : null;
    }
}

if (!function_exists('gd_client_portal_get_visible_projects')) {
    /** Legacy compatibility wrapper; visibility rules are owned by the repository. */
    function gd_client_portal_get_visible_projects($limit = -1)
    {
        if (!gd_client_portal_verify_request()) return array();
        $repo = function_exists('gdcp_repository') ? gdcp_repository('project') : null;
        if (!$repo || !method_exists($repo, 'visible')) return array();
        $bounded = $limit > 0 ? absint($limit) : 500;
        return $repo->visible($bounded);
    }
}

if (!function_exists('gd_client_portal_render_placeholder_content')) {
    function gd_client_portal_render_placeholder_content($message)
    {
        return '<p class="gd-portal-placeholder">' . esc_html($message) . '</p>';
    }
}

if (!function_exists('gd_client_portal_render_service_projects')) {
    function gd_client_portal_render_service_projects()
    {
        if (!gd_client_portal_verify_request()) {
            return __('Please log in to view your projects.', 'gd-client-portal');
        }

        $projects = gd_client_portal_get_visible_projects();
        ob_start();

        echo '<div class="gdcp-project-workspace">';
        echo '<div class="gdcp-project-workspace__header">';
        echo '<div><h2>' . esc_html__('Your Projects', 'gd-client-portal') . '</h2>';
        echo '<p>' . esc_html__('Track progress, see the current stage, and open a project workspace.', 'gd-client-portal') . '</p></div>';
        echo '<span class="gdcp-project-count">' . intval(count($projects)) . ' ' . esc_html(_n('project', 'projects', count($projects), 'gd-client-portal')) . '</span>';
        echo '</div>';

        if (!$projects) {
            echo '<div class="gdcp-project-empty">';
            echo '<h3>' . esc_html__('No active projects yet', 'gd-client-portal') . '</h3>';
            echo '<p>' . esc_html__('Your service projects will appear here once they are created and assigned to your account.', 'gd-client-portal') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="gdcp-project-grid">';
            foreach ($projects as $project) {
                $progress = max(0, min(100, intval($project->progress)));
                $status = ucwords(str_replace('_', ' ', (string) $project->status));
                $stage = ucwords(str_replace('_', ' ', (string) $project->current_stage));

                echo '<article class="gdcp-project-card">';
                echo '<div class="gdcp-project-card__top">';
                echo '<div><h3>' . esc_html($project->title) . '</h3>';
                echo '<p class="gdcp-project-stage">' . esc_html($stage ?: __('Project setup', 'gd-client-portal')) . '</p></div>';
                echo '<span class="gdcp-project-status">' . esc_html($status ?: __('Active', 'gd-client-portal')) . '</span>';
                echo '</div>';

                echo '<div class="gdcp-project-progress">';
                echo '<div class="gdcp-project-progress__labels"><span>' . esc_html__('Progress', 'gd-client-portal') . '</span><strong>' . intval($progress) . '%</strong></div>';
                echo '<div class="gdcp-project-progress__bar"><span style="width:' . intval($progress) . '%"></span></div>';
                echo '</div>';

                echo '<dl class="gdcp-project-meta">';
                echo '<div><dt>' . esc_html__('Current stage', 'gd-client-portal') . '</dt><dd>' . esc_html($stage ?: '—') . '</dd></div>';
                echo '<div><dt>' . esc_html__('Order', 'gd-client-portal') . '</dt><dd>#' . intval($project->order_id) . '</dd></div>';
                echo '</dl>';

                echo '<div class="gdcp-project-card__actions">';
                echo '<a href="javascript:gdOpenProject(' . intval($project->id) . ');" class="button button-primary">' . esc_html__('Open Project', 'gd-client-portal') . '</a>';
                echo '</div>';
                echo '</article>';
            }
            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_render_deliverables_workspace')) {
    function gd_client_portal_render_deliverables_workspace()
    {
        if (!gd_client_portal_verify_request()) {
            return __('Please log in to view your deliverables.', 'gd-client-portal');
        }

        $projects = gd_client_portal_get_visible_projects();
        ob_start();
        $has_projects = false;

        foreach ($projects as $project) {
            if (empty($project->file_url)) {
                continue;
            }
            $has_projects = true;
            echo '<div class="gd-deliverables-item">';
            echo '<h3>' . esc_html($project->title) . '</h3>';
            echo '<p><strong>' . esc_html__('Order:', 'gd-client-portal') . '</strong> #' . esc_html($project->order_id) . '</p>';
            echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html(ucwords(str_replace('_', ' ', $project->status))) . '</p>';
            echo '<p><a href="' . esc_url(gd_client_portal_private_project_file_url($project->id)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open project file', 'gd-client-portal') . '</a></p>';
            echo '<p><a href="/revision-request/">' . esc_html__('Request Revision', 'gd-client-portal') . '</a></p>';
            echo '</div>';
        }

        if (!$has_projects) {
            echo '<p>' . esc_html__('No project deliverables found.', 'gd-client-portal') . '</p>';
        }

        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_create_project_from_order')) {
    function gd_client_portal_create_project_from_order($order_id)
    {
        // WooCommerce Automation 2.0 owns paid-order project provisioning.
        // Keep this legacy entry point for compatibility with older integrations.
        if (function_exists('gd_client_portal_woo_automation_create_projects')) {
            return gd_client_portal_woo_automation_create_projects($order_id);
        }
        return array();
    }
}

if (!function_exists('gd_client_portal_submit_requirements')) {
    function gd_client_portal_submit_requirements()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            wp_send_json_error(__('Please log in to continue.', 'gd-client-portal'));
        }

        if (!gd_client_portal_verify_nonce_request('gd_client_portal_submit_requirements')) {
            wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
        }

        $table = gd_client_portal_get_project_table_name();
        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $product_id = isset($_POST['product_id']) ? absint(wp_unslash($_POST['product_id'])) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

        if (!$order_id || !$product_id) {
            wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
        }

        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE order_id = %d AND product_id = %d", $order_id, $product_id));
        if (!$project) {
            wp_send_json_error(__('Project not found.', 'gd-client-portal'));
        }

        if (!gd_client_portal_verify_project_access($project)) {
            wp_send_json_error(__('You do not have permission to edit this project.', 'gd-client-portal'));
        }

        $file_url = '';

        if (!empty($_FILES['file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';

            $max_size = 5 * 1024 * 1024; // 5MB
            $allowed_exts = array('pdf','doc','docx','jpg','jpeg','png','gif','zip','rar','txt','xlsx','xls','ppt','pptx');

            $file = $_FILES['file'];
            if ($file['size'] > $max_size) {
                wp_send_json_error(__('File exceeds maximum allowed size (5MB).', 'gd-client-portal'));
            }

            $file['name'] = sanitize_file_name($file['name']);
            $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $ext = !empty($check['ext']) ? strtolower($check['ext']) : '';
            if (empty($ext) || !in_array($ext, $allowed_exts, true)) {
                wp_send_json_error(__('File type not allowed.', 'gd-client-portal'));
            }

            $allowed_mimes = array('jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation','txt'=>'text/plain','zip'=>'application/zip');
            $upload = wp_handle_upload($file, array('test_form' => false, 'mimes' => $allowed_mimes));

            if (!empty($upload['url'])) {
                $file_url = $upload['url'];
            }
        }

        $service = function_exists('gdcp_service') ? gdcp_service('project') : null;
        if (!$service || !method_exists($service, 'submit_requirements')) {
            wp_send_json_error(__('Project service unavailable.', 'gd-client-portal'), 503);
        }
        $updated = $service->submit_requirements($project->id, $title, $description, $file_url);
        if (!$updated) {
            wp_send_json_error(__('Unable to save requirements.', 'gd-client-portal'));
        }
        $project = $service->get($project->id);
        do_action('gd_client_portal_requirements_submitted', $project);

        // Notify project owner/admin about submitted requirements
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE order_id = %d AND product_id = %d", $order_id, $product_id));
        if ($project) {
            $owner = gd_client_portal_cached_user($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $subject = sprintf(__('Requirements submitted for project: %s', 'gd-client-portal'), $project->title);
            $message_body = sprintf("%s\n\n%s\n\n%s: %s", __('A client has submitted requirements for the project.', 'gd-client-portal'), $project->title, __('Message', 'gd-client-portal'), $description);
            if (!empty($file_url)) {
                $message_body .= "\n\n" . __('An attachment was added. Sign in to the portal to access it securely.', 'gd-client-portal');
            }
            wp_mail($to, $subject, $message_body);
        }

        wp_send_json_success(__('Requirements submitted successfully.', 'gd-client-portal'));
    }
}

if (!function_exists('gd_client_portal_update_project_stage')) {
    function gd_client_portal_update_project_stage($project_id, $new_stage, $progress = null)
    {
        if (function_exists('gdcp_service')) {
            $service = gdcp_service('project');
            if ($service && method_exists($service, 'update_stage')) {
                return $service->update_stage($project_id, $new_stage, $progress);
            }
        }
        return false;
    }
}

if (!function_exists('gd_client_portal_auto_progress')) {
    function gd_client_portal_auto_progress($service_type, $current_stage)
    {
        $workflow = gd_client_portal_get_workflow($service_type);
        $index = array_search($current_stage, $workflow, true);

        if ($index === false) {
            return 0;
        }

        $total = max(count($workflow) - 1, 1);

        return intval(($index / $total) * 100);
    }
}

if (!function_exists('gd_client_portal_render_workflow_ui')) {
    function gd_client_portal_render_workflow_ui()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            return __('Please log in to view your workflow.', 'gd-client-portal');
        }

        $projects = gd_client_portal_get_visible_projects(1);
        $project = !empty($projects) ? $projects[0] : null;

        if (!$project) {
            return __('No active project found.', 'gd-client-portal');
        }

        $workflow = gd_client_portal_get_workflow($project->service_type);
        ob_start();

        echo '<div class="gd-workflow-ui">';
        foreach ($workflow as $stage) {
            $active_class = ($stage === $project->current_stage) ? ' gd-workflow-stage-active' : '';
            echo '<div class="gd-workflow-stage' . esc_attr($active_class) . '">';
            echo '<h4>' . esc_html(ucwords(str_replace('_', ' ', $stage))) . '</h4>';

            if ($stage === $project->current_stage) {
                echo '<p>' . esc_html__('▶ Current Stage', 'gd-client-portal') . '</p>';
            }

            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_render_project_vault')) {
    function gd_client_portal_render_project_vault()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            return __('Please log in to view this project.', 'gd-client-portal');
        }

        $project_id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
        if (!$project_id) {
            return __('Invalid project', 'gd-client-portal');
        }

        $table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $project_id));

        if (!$project) {
            return __('Project not found', 'gd-client-portal');
        }

        if (!gd_client_portal_verify_project_access($project)) {
            return __('You do not have permission to view this project.', 'gd-client-portal');
        }

        ob_start();

        echo '<div class="gd-project-vault">';
        echo '<h2>' . esc_html__('Project Vault', 'gd-client-portal') . '</h2>';
        echo '<p><strong>' . esc_html__('Title:', 'gd-client-portal') . '</strong> ' . esc_html($project->title) . '</p>';
        echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html($project->status) . '</p>';
        echo '<p><strong>' . esc_html__('Stage:', 'gd-client-portal') . '</strong> ' . esc_html($project->current_stage) . '</p>';
        echo '<p><strong>' . esc_html__('Progress:', 'gd-client-portal') . '</strong> ' . intval($project->progress) . '%</p>';
        echo '<hr />';
        echo '<p>' . esc_html__('Deliverables will appear here via WP Customer Area.', 'gd-client-portal') . '</p>';
        echo '<p>' . esc_html__('Messages module can be attached here later.', 'gd-client-portal') . '</p>';
        echo '</div>';

        return ob_get_clean();
    }
}


if (!function_exists('gd_client_portal_render_project_workspace')) {
    /**
     * Full project command center. All project data is fetched through the
     * existing tenant/ownership authorization boundary before rendering.
     */
    function gd_client_portal_render_project_workspace($atts = array())
    {
        if (!gd_client_portal_verify_request()) {
            return gd_client_portal_render_access_gate();
        }

        $project_id = isset($_GET['project_id']) ? absint(wp_unslash($_GET['project_id'])) : 0;
        $projects = gd_client_portal_get_visible_projects();
        $project = $project_id ? gd_client_portal_cached_project($project_id) : null;

        if ($project_id && (!$project || !gd_client_portal_verify_project_access($project))) {
            return '<div class="gd-workspace-notice gd-workspace-error">' . esc_html__('You do not have permission to access this project.', 'gd-client-portal') . '</div>';
        }

        if (!$project) {
            ob_start();
            echo '<div class="gd-project-workspace gd-workspace-index">';
            echo '<div class="gd-workspace-hero"><div><span class="gd-workspace-eyebrow">' . esc_html__('Client Workspace', 'gd-client-portal') . '</span><h2>' . esc_html__('Your Projects', 'gd-client-portal') . '</h2><p>' . esc_html__('Open a project to manage requirements, workflow, files, deliverables, and communication from one workspace.', 'gd-client-portal') . '</p></div></div>';
            if (empty($projects)) {
                echo '<div class="gd-workspace-empty"><strong>' . esc_html__('No projects yet', 'gd-client-portal') . '</strong><span>' . esc_html__('Your service projects will appear here after a qualifying order is created.', 'gd-client-portal') . '</span></div>';
            } else {
                echo '<div class="gd-workspace-project-grid">';
                foreach ($projects as $item) {
                    $url = add_query_arg('project_id', absint($item->id), get_permalink());
                    $progress = max(0, min(100, intval($item->progress)));
                    echo '<article class="gd-workspace-project-card">';
                    echo '<div class="gd-workspace-project-top"><span class="gd-workspace-icon">▣</span><span class="gd-workspace-status">' . esc_html(ucwords(str_replace('_', ' ', $item->status))) . '</span></div>';
                    echo '<h3>' . esc_html($item->title) . '</h3>';
                    echo '<p class="gd-workspace-stage">' . esc_html(ucwords(str_replace('_', ' ', $item->current_stage))) . '</p>';
                    echo '<div class="gd-workspace-progress"><span style="width:' . $progress . '%"></span></div><div class="gd-workspace-progress-meta"><span>' . esc_html__('Progress', 'gd-client-portal') . '</span><strong>' . $progress . '%</strong></div>';
                    echo '<a class="gd-workspace-button" href="' . esc_url($url) . '">' . esc_html__('Open workspace', 'gd-client-portal') . ' <span>→</span></a>';
                    echo '</article>';
                }
                echo '</div>';
            }
            echo '</div>';
            return ob_get_clean();
        }

        $messages = gdcp_message_service()->list_for_project($project->id, 20, 'DESC');
        $workflow = gd_client_portal_get_workflow($project->service_type);
        $current_index = array_search($project->current_stage, $workflow, true);
        $current_index = ($current_index === false) ? 0 : $current_index;
        $progress = max(0, min(100, intval($project->progress)));
        $can_manage_stage = current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin();
        $back_url = remove_query_arg('project_id');
        $nonce = wp_create_nonce('gd_client_portal_project_modal');

        ob_start();
        echo '<div class="gd-project-workspace" data-project-id="' . intval($project->id) . '">';
        echo '<div class="gd-workspace-hero gd-workspace-hero-project"><div><a class="gd-workspace-back" href="' . esc_url($back_url) . '">← ' . esc_html__('All projects', 'gd-client-portal') . '</a><span class="gd-workspace-eyebrow">' . esc_html__('Project Workspace', 'gd-client-portal') . '</span><h2>' . esc_html($project->title) . '</h2><p>' . esc_html__('One secure place for your project requirements, progress, files, deliverables and conversations.', 'gd-client-portal') . '</p></div><div class="gd-workspace-progress-ring"><strong>' . $progress . '%</strong><span>' . esc_html__('complete', 'gd-client-portal') . '</span></div></div>';

        echo '<div class="gd-workspace-stat-grid">';
        echo '<div><span>' . esc_html__('Status', 'gd-client-portal') . '</span><strong>' . esc_html(ucwords(str_replace('_', ' ', $project->status))) . '</strong></div>';
        echo '<div><span>' . esc_html__('Current stage', 'gd-client-portal') . '</span><strong>' . esc_html(ucwords(str_replace('_', ' ', $project->current_stage))) . '</strong></div>';
        echo '<div><span>' . esc_html__('Order', 'gd-client-portal') . '</span><strong>#' . intval($project->order_id) . '</strong></div>';
        echo '<div><span>' . esc_html__('Started', 'gd-client-portal') . '</span><strong>' . esc_html($project->created_at) . '</strong></div>';
        echo '</div>';

        echo '<div class="gd-workspace-layout">';
        echo '<main class="gd-workspace-main">';

        echo '<section class="gd-workspace-panel"><div class="gd-workspace-panel-head"><div><span class="gd-workspace-eyebrow">01</span><h3>' . esc_html__('Workflow', 'gd-client-portal') . '</h3></div><span>' . $progress . '%</span></div>';
        echo '<div class="gd-workspace-timeline">';
        foreach ($workflow as $i => $stage) {
            $state = $i < $current_index ? 'is-complete' : ($i === $current_index ? 'is-current' : '');
            echo '<div class="gd-workspace-stage ' . esc_attr($state) . '"><span class="gd-workspace-stage-dot">' . ($i < $current_index ? '✓' : ($i + 1)) . '</span><div><strong>' . esc_html(ucwords(str_replace('_', ' ', $stage))) . '</strong>';
            if ($i === $current_index) echo '<small>' . esc_html__('Current stage', 'gd-client-portal') . '</small>';
            echo '</div></div>';
        }
        echo '</div>';
        if ($can_manage_stage) {
            echo '<div class="gd-workspace-stage-actions"><label>' . esc_html__('Update workflow stage', 'gd-client-portal') . '</label><select class="gd-workspace-stage-select" data-project-id="' . intval($project->id) . '">';
            foreach ($workflow as $stage) echo '<option value="' . esc_attr($stage) . '"' . selected($stage, $project->current_stage, false) . '>' . esc_html(ucwords(str_replace('_', ' ', $stage))) . '</option>';
            echo '</select><button type="button" class="gd-workspace-button gd-workspace-update-stage">' . esc_html__('Save stage', 'gd-client-portal') . '</button><span class="gd-workspace-action-status" aria-live="polite"></span></div>';
        }
        echo '</section>';

        echo '<section class="gd-workspace-panel"><div class="gd-workspace-panel-head"><div><span class="gd-workspace-eyebrow">02</span><h3>' . esc_html__('Requirements', 'gd-client-portal') . '</h3></div></div>';
        if (!empty($project->description)) echo '<div class="gd-workspace-requirements-copy">' . nl2br(esc_html($project->description)) . '</div>';
        echo '<form class="gd-workspace-requirements-form" enctype="multipart/form-data"><input type="hidden" name="action" value="gd_submit_requirements"><input type="hidden" name="order_id" value="' . intval($project->order_id) . '"><input type="hidden" name="product_id" value="' . intval($project->product_id) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('gd_client_portal_submit_requirements')) . '"><textarea name="description" rows="4" placeholder="' . esc_attr__('Add or update your project requirements…', 'gd-client-portal') . '"></textarea><div class="gd-workspace-upload-row"><input type="file" name="file"><button type="submit" class="gd-workspace-button">' . esc_html__('Submit requirements', 'gd-client-portal') . '</button></div><span class="gd-workspace-action-status" aria-live="polite"></span></form>';
        if (!empty($project->file_url)) echo '<div class="gd-workspace-file-card"><span>📎</span><div><strong>' . esc_html__('Latest project attachment', 'gd-client-portal') . '</strong><a href="' . esc_url(gd_client_portal_private_project_file_url($project->id)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Open attachment', 'gd-client-portal') . '</a></div></div>';
        echo '</section>';

        if (function_exists('gd_client_portal_render_delivery_panel')) { echo gd_client_portal_render_delivery_panel($project); }
        if (function_exists('gd_client_portal_render_approval_panel')) echo gd_client_portal_render_approval_panel($project);
        echo '</main>';

        echo '<aside class="gd-workspace-side">';
        echo '<section class="gd-workspace-panel gd-workspace-message-panel"><div class="gd-workspace-panel-head"><div><span class="gd-workspace-eyebrow">04</span><h3>' . esc_html__('Project messages', 'gd-client-portal') . '</h3></div></div>';
        echo '<div class="gd-workspace-messages">';
        if ($messages) foreach ($messages as $m) { $author = gd_client_portal_cached_user($m->user_id); echo '<article class="gd-workspace-message"><div class="gd-workspace-message-meta"><strong>' . esc_html($author ? $author->display_name : __('Portal user', 'gd-client-portal')) . '</strong><time>' . esc_html($m->created_at) . '</time></div><p>' . nl2br(esc_html($m->message)) . '</p>'; if (!empty($m->file_url)) echo '<a href="' . esc_url(gd_client_portal_private_message_file_url($m->id)) . '" target="_blank" rel="noopener noreferrer">📎 ' . esc_html__('Attachment', 'gd-client-portal') . '</a>'; echo '</article>'; }
        else echo '<div class="gd-workspace-empty compact"><span>' . esc_html__('No messages yet. Start the conversation below.', 'gd-client-portal') . '</span></div>';
        echo '</div>';
        echo '<form class="gd-workspace-message-form" enctype="multipart/form-data"><input type="hidden" name="project_id" value="' . intval($project->id) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr(wp_create_nonce('gd_client_portal_project_message')) . '"><textarea name="message" rows="4" placeholder="' . esc_attr__('Write a project update or question…', 'gd-client-portal') . '"></textarea><input type="file" name="message_file"><button type="submit" class="gd-workspace-button">' . esc_html__('Send message', 'gd-client-portal') . '</button><span class="gd-workspace-action-status" aria-live="polite"></span></form></section>';
        echo '</aside></div></div>';
        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_render_project_modal')) {
    function gd_client_portal_render_project_modal()
    {
        $nonce = wp_create_nonce('gd_client_portal_project_modal');
        $ajax_url = esc_url(admin_url('admin-ajax.php'));

        ob_start();
        ?>
        <div id="gd-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999;">
            <div style="max-width:700px; margin:80px auto; background:#fff; padding:24px; border-radius:12px; position:relative;">
                <button type="button" onclick="gdCloseModal();" style="position:absolute; top:12px; right:12px; border:0; background:none; font-size:24px; cursor:pointer;">&times;</button>
                <div id="gd-modal-body">Loading...</div>
            </div>
        </div>
        <script>
        function gdOpenProject(id) {
            document.getElementById('gd-modal').style.display = 'block';
            fetch('<?php echo $ajax_url; ?>?action=gd_get_project&id=' + encodeURIComponent(id) + '&_wpnonce=<?php echo esc_attr($nonce); ?>')
                .then(function(res) { return res.text(); })
                .then(function(data) {
                    document.getElementById('gd-modal-body').innerHTML = data;
                });
        }
        function gdCloseModal() {
            document.getElementById('gd-modal').style.display = 'none';
        }
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_get_project_ajax')) {
    function gd_client_portal_get_project_ajax()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            echo __('Please log in to view this project.', 'gd-client-portal');
            wp_die();
        }

        if (!gd_client_portal_verify_nonce_request('gd_client_portal_project_modal')) {
            echo __('Invalid request.', 'gd-client-portal');
            wp_die();
        }

        $project_id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
        $table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $project_id));

        if (!$project) {
            echo __('Project not found', 'gd-client-portal');
            wp_die();
        }

        // Enforce tenant + ownership access for the requested project.
        if (!gd_client_portal_verify_project_access($project)) {
            echo __('You do not have permission to view this project.', 'gd-client-portal');
            wp_die();
        }

        echo '<h3>' . esc_html($project->title) . '</h3>';
        echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html($project->status) . '</p>';
        echo '<p><strong>' . esc_html__('Stage:', 'gd-client-portal') . '</strong> ' . esc_html($project->current_stage) . '</p>';
        echo '<p><strong>' . esc_html__('Progress:', 'gd-client-portal') . '</strong> ' . intval($project->progress) . '%</p>';

        // Messages
        $messages = gdcp_message_service()->list_for_project($project->id, 300, 'ASC');
        echo '<h4>' . esc_html__('Messages', 'gd-client-portal') . '</h4>';
        echo '<div class="gd-project-messages">';
        if ($messages) {
            foreach ($messages as $m) {
                $author = gd_client_portal_cached_user($m->user_id);
                $name = $author ? esc_html($author->display_name) : esc_html__('Guest', 'gd-client-portal');
                echo '<div class="gd-message-item">';
                echo '<div class="gd-message-meta"><strong>' . $name . '</strong> <span class="gd-message-date">' . esc_html($m->created_at) . '</span></div>';
                echo '<div class="gd-message-body">' . wp_kses_post(nl2br(esc_html($m->message))) . '</div>';
                if (!empty($m->file_url)) {
                    echo '<div class="gd-message-file"><a href="' . esc_url(gd_client_portal_private_message_file_url($m->id)) . '" target="_blank">' . esc_html__('Download file', 'gd-client-portal') . '</a></div>';
                }
                echo '</div>';
            }
        } else {
            echo '<p>' . esc_html__('No messages yet.', 'gd-client-portal') . '</p>';
        }
        echo '</div>';

        // Message form
        echo '<form class="gd-project-message-form" enctype="multipart/form-data">';
        echo wp_nonce_field('gd_client_portal_project_message', 'gd_client_portal_project_message_nonce', true, false);
        echo '<input type="hidden" name="project_id" value="' . intval($project->id) . '" />';
        echo '<p><textarea name="message" rows="4" style="width:100%" placeholder="' . esc_attr__('Write a message...', 'gd-client-portal') . '"></textarea></p>';
        echo '<p><input type="file" name="message_file" /></p>';
        echo '<p><button class="button" type="submit">' . esc_html__('Send Message', 'gd-client-portal') . '</button></p>';
        echo '</form>';

        // Stage actions
        echo '<div class="gd-project-stage-actions" style="margin-top:12px;">';
        $workflow = gd_client_portal_get_workflow($project->service_type);
        foreach ($workflow as $stage) {
            $active = ($stage === $project->current_stage) ? ' gd-workflow-stage-active' : '';
            echo '<button class="button gd-project-stage-btn' . $active . '" data-project-id="' . intval($project->id) . '" data-stage="' . esc_attr($stage) . '">' . esc_html(ucwords(str_replace('_', ' ', $stage))) . '</button> ';
        }
        echo '</div>';
        wp_die();
    }
}

add_shortcode('gd_service_projects', 'gd_client_portal_render_service_projects');
add_shortcode('gd_project_deliverables', 'gd_client_portal_render_deliverables_workspace');
add_shortcode('gd_workflow_ui', 'gd_client_portal_render_workflow_ui');
add_shortcode('gd_project_vault', 'gd_client_portal_render_project_vault');
add_shortcode('gd_project_modal', 'gd_client_portal_render_project_modal');
add_shortcode('gd_project_workspace', 'gd_client_portal_render_project_workspace');

add_action('woocommerce_thankyou', 'gd_client_portal_service_order_thankyou_prompt', 20);
add_action('wp_ajax_gd_submit_requirements', 'gd_client_portal_submit_requirements');
add_action('wp_ajax_gd_get_project', 'gd_client_portal_get_project_ajax');

if (!function_exists('gd_client_portal_ajax_add_message')) {
    function gd_client_portal_ajax_add_message()
    {
        gd_client_portal_ajax_guard('gd_client_portal_project_message','_wpnonce');

        $project_id = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (!$project_id || empty($message)) {
            wp_send_json_error(__('Please provide a message.', 'gd-client-portal'));
        }

        // Authorize the project BEFORE accepting an attachment or writing a message.
        $proj_table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $proj_table WHERE id = %d", $project_id));
        if (!$project || !gd_client_portal_verify_project_access($project)) {
            wp_send_json_error(__('You do not have permission to post to this project.', 'gd-client-portal'));
        }

        $file_url = '';
        if (!empty($_FILES['message_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $allowed_mimes = array('jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation','txt'=>'text/plain','zip'=>'application/zip');
            $validation = gd_client_portal_validate_upload($_FILES['message_file'], $allowed_mimes, apply_filters('gd_client_portal_message_attachment_max_upload_size', 10 * MB_IN_BYTES));
            if (is_wp_error($validation)) {
                wp_send_json_error(array('message' => $validation->get_error_message()), 400);
            }
            $upload = wp_handle_upload($_FILES['message_file'], array('test_form' => false, 'mimes' => $allowed_mimes));
            if (!empty($upload['error']) || empty($upload['url'])) {
                wp_send_json_error(array('message' => !empty($upload['error']) ? $upload['error'] : __('Attachment upload failed.', 'gd-client-portal')), 400);
            }
            $file_url = esc_url_raw($upload['url']);
        }

        $event_key = isset($_POST['client_message_key']) ? sanitize_key(wp_unslash($_POST['client_message_key'])) : '';
        if (!$event_key) { $event_key = wp_generate_uuid4(); }
        $created = gdcp_message_service()->create(array(
            'project_id' => $project_id,
            'user_id' => gd_client_portal_cached_current_user_id(),
            'message' => $message,
            'file_url' => $file_url,
            'event_key' => $event_key,
        ));
        if (is_wp_error($created)) {
            wp_send_json_error($created->get_error_message());
        }
        $inserted = is_object($created) ? absint($created->id) : absint($created);

        do_action('gd_client_portal_project_message_added', $project_id, gd_client_portal_cached_current_user_id());

        // Notify project owner and admin by email
        if ($project) {
            $owner = gd_client_portal_cached_user($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $author = gd_client_portal_cached_user(gd_client_portal_cached_current_user_id());
            $author_name = $author ? $author->display_name : __('Client', 'gd-client-portal');
            $subject = sprintf(__('New message on project: %s', 'gd-client-portal'), $project->title);
            $body = sprintf("%s\n\n%s: %s\n\n%s\n\n%s: %s", __('A new message was posted on your project.', 'gd-client-portal'), __('Project', 'gd-client-portal'), $project->title, $message, __('From', 'gd-client-portal'), $author_name);
            if (!empty($file_url)) {
                $body .= "\n\n" . __('An attachment was added. Sign in to the portal to access it securely.', 'gd-client-portal');
            }
            wp_mail($to, $subject, $body);
            // also notify admin
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        wp_send_json_success(array('message' => __('Message posted.', 'gd-client-portal')));
    }
}

if (!function_exists('gd_client_portal_ajax_update_stage')) {
    function gd_client_portal_ajax_update_stage()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            wp_send_json_error(__('Please log in to continue.', 'gd-client-portal'));
        }

        if (!gd_client_portal_verify_nonce_request('gd_client_portal_project_modal')) {
            wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
        }

        $project_id = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
        $new_stage = isset($_POST['new_stage']) ? sanitize_text_field(wp_unslash($_POST['new_stage'])) : '';

        if (!$project_id || empty($new_stage)) {
            wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
        }

        $table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $project_id));
        if (!$project) {
            wp_send_json_error(__('Project not found.', 'gd-client-portal'));
        }

        if (!gd_client_portal_verify_project_access($project) || (!current_user_can('manage_options') && !gd_client_portal_user_is_tenant_admin())) {
            wp_send_json_error(__('You do not have permission to update this project stage.', 'gd-client-portal'));
        }

        $workflow = gd_client_portal_get_workflow($project->service_type);
        if (!in_array($new_stage, $workflow, true)) {
            wp_send_json_error(__('Invalid workflow stage.', 'gd-client-portal'));
        }

        $progress = gd_client_portal_auto_progress($project->service_type, $new_stage);
        if (!gd_client_portal_update_project_stage($project_id, $new_stage, $progress)) {
            wp_send_json_error(__('Unable to update this project.', 'gd-client-portal'));
        }
        // Notify project owner/admin of stage update
        $proj_table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $proj_table WHERE id = %d", $project_id));
        if ($project) {
            $owner = gd_client_portal_cached_user($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $subject = sprintf(__('Project stage updated: %s', 'gd-client-portal'), $project->title);
            $body = sprintf("%s\n\n%s: %s\n%s: %s", __('The project stage has been updated.', 'gd-client-portal'), __('Project', 'gd-client-portal'), $project->title, __('New Stage', 'gd-client-portal'), $new_stage);
            wp_mail($to, $subject, $body);
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        wp_send_json_success(array('message' => __('Project stage updated.', 'gd-client-portal'), 'progress' => $progress));
    }
}


/** Securely download the latest project attachment after object-level authorization. */
function gd_client_portal_project_file_download() {
    $project_id = absint($_GET['project_id'] ?? 0);
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!$project_id || !wp_verify_nonce($nonce, 'gd_client_portal_project_file_download_' . $project_id)) {
        wp_die(esc_html__('Invalid download request.', 'gd-client-portal'), '', array('response' => 403));
    }
    if (!gd_client_portal_verify_request()) {
        wp_die(esc_html__('Please sign in.', 'gd-client-portal'), '', array('response' => 403));
    }
    global $wpdb;
    $project = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . gd_client_portal_get_project_table_name() . ' WHERE id = %d', $project_id));
    if (!$project || !gd_client_portal_verify_project_access($project) || empty($project->file_url)) {
        wp_die(esc_html__('You do not have access to this file.', 'gd-client-portal'), '', array('response' => 403));
    }
    gd_client_portal_stream_private_file($project->file_url, basename(parse_url($project->file_url, PHP_URL_PATH)));
}

/** Securely download a project message attachment after project authorization. */
function gd_client_portal_message_file_download() {
    $message_id = absint($_GET['message_id'] ?? 0);
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!$message_id || !wp_verify_nonce($nonce, 'gd_client_portal_message_file_download_' . $message_id)) {
        wp_die(esc_html__('Invalid download request.', 'gd-client-portal'), '', array('response' => 403));
    }
    if (!gd_client_portal_verify_request()) {
        wp_die(esc_html__('Please sign in.', 'gd-client-portal'), '', array('response' => 403));
    }
    $message = gdcp_message_service()->find($message_id);
    if (!$message || empty($message->file_url)) {
        wp_die(esc_html__('File unavailable.', 'gd-client-portal'), '', array('response' => 404));
    }
    $project = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . gd_client_portal_get_project_table_name() . ' WHERE id = %d', absint($message->project_id)));
    if (!$project || !gd_client_portal_verify_project_access($project)) {
        wp_die(esc_html__('You do not have access to this file.', 'gd-client-portal'), '', array('response' => 403));
    }
    gd_client_portal_stream_private_file($message->file_url, basename(parse_url($message->file_url, PHP_URL_PATH)));
}

add_action('admin_post_gd_client_portal_project_file_download', 'gd_client_portal_project_file_download');
add_action('admin_post_gd_client_portal_message_file_download', 'gd_client_portal_message_file_download');

add_action('wp_ajax_gd_client_portal_add_message', 'gd_client_portal_ajax_add_message');
add_action('wp_ajax_gd_client_portal_update_stage', 'gd_client_portal_ajax_update_stage');

if (!function_exists('gd_client_portal_render_projects_list')) {
    function gd_client_portal_render_projects_list($atts = array())
    {
        if (!gd_client_portal_verify_request()) {
            return gd_client_portal_render_placeholder_content(__('Please log in to view your projects.', 'gd-client-portal'));
        }

        $projects = gd_client_portal_get_visible_projects();

        ob_start();

        echo '<div class="gd-projects-list">';
        echo '<h2>' . esc_html__('Your Projects', 'gd-client-portal') . '</h2>';

        if (!$projects) {
            echo '<p>' . esc_html__('No projects found.', 'gd-client-portal') . '</p>';
            echo '</div>';
            return ob_get_clean();
        }

        echo '<div class="gd-cards-grid">';
        foreach ($projects as $project) {
            echo '<div class="gd-card">';
            echo '<div class="gd-card-header"><div class="gd-card-icon">📁</div><h3>' . esc_html($project->title) . '</h3></div>';
            echo '<div class="gd-card-description">' . esc_html__('Status:', 'gd-client-portal') . ' ' . esc_html($project->status) . ' — ' . esc_html(ucwords(str_replace('_', ' ', $project->current_stage))) . '</div>';
            echo '<div class="gd-card-content">';
            echo '<p><strong>' . esc_html__('Progress:', 'gd-client-portal') . '</strong> ' . intval($project->progress) . '%</p>';
            echo '<p><a href="javascript:gdOpenProject(' . intval($project->id) . ');" class="button">' . esc_html__('View Project', 'gd-client-portal') . '</a></p>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>'; // grid
        echo '</div>';

        return ob_get_clean();
    }
}

add_shortcode('gd_projects', 'gd_client_portal_render_projects_list');
