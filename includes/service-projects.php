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

        if (!gd_client_portal_is_woocommerce_active()) {
            return __('WooCommerce is required.', 'gd-client-portal');
        }

        $user_id = get_current_user_id();
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => -1,
            'status' => array('processing', 'completed', 'on-hold'),
        ));

        ob_start();
        $found_project = false;

        if (!empty($orders)) {
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $is_service = get_post_meta($product_id, 'is_service_product', true);

                    if ($is_service !== '1') {
                        continue;
                    }

                    $found_project = true;
                    $status = get_post_meta($order->get_id(), 'project_status', true);
                    $service_name = $item->get_name();

                    echo '<div class="gd-service-project">';
                    echo '<h3>' . esc_html($service_name) . '</h3>';
                    echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html($status) . '</p>';
                    echo '<p><strong>' . esc_html__('Order ID:', 'gd-client-portal') . '</strong> #' . esc_html($order->get_id()) . '</p>';
                    echo '</div>';
                }
            }
        }

        if (!$found_project) {
            echo '<p>' . esc_html__('No active service projects found.', 'gd-client-portal') . '</p>';
        }

        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_render_deliverables_workspace')) {
    function gd_client_portal_render_deliverables_workspace()
    {
        if (!gd_client_portal_verify_request()) {
            return __('Please log in to view your deliverables.', 'gd-client-portal');
        }

        if (!gd_client_portal_is_woocommerce_active()) {
            return __('WooCommerce is required.', 'gd-client-portal');
        }

        $user_id = get_current_user_id();
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => -1,
            'status' => array('processing', 'completed', 'on-hold'),
        ));

        ob_start();
        $has_projects = false;

        if (!empty($orders)) {
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $is_service = get_post_meta($product_id, 'is_service_product', true);

                    if ($is_service !== '1') {
                        continue;
                    }

                    $has_projects = true;
                    $status = get_post_meta($order->get_id(), 'project_status', true);
                    if (empty($status)) {
                        $status = 'pending_requirements';
                    }

                    $service_name = $item->get_name();

                    echo '<div class="gd-deliverables-item">';
                    echo '<h3>' . esc_html($service_name) . '</h3>';
                    echo '<p><strong>' . esc_html__('Order:', 'gd-client-portal') . '</strong> #' . esc_html($order->get_id()) . '</p>';
                    echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html(ucwords(str_replace('_', ' ', $status))) . '</p>';
                    echo '<p><a href="/my-files/">' . esc_html__('Open Secure Files', 'gd-client-portal') . '</a></p>';
                    echo '<p><a href="/revision-request/">' . esc_html__('Request Revision', 'gd-client-portal') . '</a></p>';
                    echo '</div>';
                }
            }
        }

        if (!$has_projects) {
            echo '<p>' . esc_html__('No active service projects found.', 'gd-client-portal') . '</p>';
        }

        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_create_project_from_order')) {
    function gd_client_portal_create_project_from_order($order_id)
    {
        global $wpdb;

        if (!gd_client_portal_is_woocommerce_active()) {
            return;
        }

        $table = gd_client_portal_get_project_table_name();
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();

            if (!has_term('service', 'product_cat', $product_id)) {
                continue;
            }

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id = %d AND product_id = %d", $order_id, $product_id));

            if ($exists) {
                continue;
            }

            $service_type = gd_client_portal_get_service_type($product_id);
            $workflow = gd_client_portal_get_workflow($service_type);
            $first_stage = $workflow[0] ?? 'intake';

            // Determine tenant id from order meta if available
            $order_tenant = 0;
            if (is_a($order, 'WC_Order')) {
                $order_tenant = intval($order->get_meta('_gd_mp_order_tenant_id')) ?: 0;
            }

            $wpdb->insert(
                $table,
                array(
                    'tenant_id' => $order_tenant,
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'product_id' => $product_id,
                    'service_type' => $service_type,
                    'title' => 'Service Project #' . $order_id,
                    'status' => 'pending',
                    'current_stage' => $first_stage,
                    'progress' => 0,
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d')
            );
        }
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

        if (!gd_client_portal_verify_tenant_access(isset($project->tenant_id) ? $project->tenant_id : 0)) {
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

            $upload = wp_handle_upload($file, array('test_form' => false));

            if (!empty($upload['url'])) {
                $file_url = $upload['url'];
            }
        }

        $updated = $wpdb->update(
            $table,
            array(
                'title' => $title,
                'description' => $description,
                'status' => 'processing',
                'current_stage' => 'requirement_analysis',
                'progress' => 10,
                'file_url' => $file_url,
            ),
            array(
                'order_id' => $order_id,
                'product_id' => $product_id,
            )
        );

        if ($updated === false) {
            wp_send_json_error(__('Unable to save requirements.', 'gd-client-portal'));
        }

        // Notify project owner/admin about submitted requirements
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE order_id = %d AND product_id = %d", $order_id, $product_id));
        if ($project) {
            $owner = get_userdata($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $subject = sprintf(__('Requirements submitted for project: %s', 'gd-client-portal'), $project->title);
            $message_body = sprintf("%s\n\n%s\n\n%s: %s", __('A client has submitted requirements for the project.', 'gd-client-portal'), $project->title, __('Message', 'gd-client-portal'), $description);
            if (!empty($file_url)) {
                $message_body .= "\n\n" . __('Attached file:', 'gd-client-portal') . ' ' . $file_url;
            }
            wp_mail($to, $subject, $message_body);
        }

        wp_send_json_success(__('Requirements submitted successfully.', 'gd-client-portal'));
    }
}

if (!function_exists('gd_client_portal_update_project_stage')) {
    function gd_client_portal_update_project_stage($project_id, $new_stage, $progress = null)
    {
        global $wpdb;

        $table = gd_client_portal_get_project_table_name();
        $data = array('current_stage' => $new_stage);

        if ($progress !== null) {
            $data['progress'] = intval($progress);
        }

        $wpdb->update($table, $data, array('id' => intval($project_id)));
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

        $user_id = get_current_user_id();
        $table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id));

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

        // Enforce tenant access for the requested project
        if (!gd_client_portal_verify_tenant_access(isset($project->tenant_id) ? $project->tenant_id : 0)) {
            echo __('You do not have permission to view this project.', 'gd-client-portal');
            wp_die();
        }

        echo '<h3>' . esc_html($project->title) . '</h3>';
        echo '<p><strong>' . esc_html__('Status:', 'gd-client-portal') . '</strong> ' . esc_html($project->status) . '</p>';
        echo '<p><strong>' . esc_html__('Stage:', 'gd-client-portal') . '</strong> ' . esc_html($project->current_stage) . '</p>';
        echo '<p><strong>' . esc_html__('Progress:', 'gd-client-portal') . '</strong> ' . intval($project->progress) . '%</p>';

        // Messages
        $messages_table = $wpdb->prefix . 'gd_project_messages';
        $messages = $wpdb->get_results($wpdb->prepare("SELECT * FROM $messages_table WHERE project_id = %d ORDER BY id ASC", $project->id));
        echo '<h4>' . esc_html__('Messages', 'gd-client-portal') . '</h4>';
        echo '<div class="gd-project-messages">';
        if ($messages) {
            foreach ($messages as $m) {
                $author = get_userdata($m->user_id);
                $name = $author ? esc_html($author->display_name) : esc_html__('Guest', 'gd-client-portal');
                echo '<div class="gd-message-item">';
                echo '<div class="gd-message-meta"><strong>' . $name . '</strong> <span class="gd-message-date">' . esc_html($m->created_at) . '</span></div>';
                echo '<div class="gd-message-body">' . wp_kses_post(nl2br(esc_html($m->message))) . '</div>';
                if (!empty($m->file_url)) {
                    echo '<div class="gd-message-file"><a href="' . esc_url($m->file_url) . '" target="_blank">' . esc_html__('Download file', 'gd-client-portal') . '</a></div>';
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

add_action('woocommerce_order_status_processing', 'gd_client_portal_create_project_from_order');
add_action('woocommerce_order_status_completed', 'gd_client_portal_create_project_from_order');
add_action('woocommerce_order_status_on-hold', 'gd_client_portal_create_project_from_order');
add_action('woocommerce_thankyou', 'gd_client_portal_service_order_thankyou_prompt', 20);
add_action('wp_ajax_gd_submit_requirements', 'gd_client_portal_submit_requirements');
add_action('wp_ajax_gd_get_project', 'gd_client_portal_get_project_ajax');

if (!function_exists('gd_client_portal_ajax_add_message')) {
    function gd_client_portal_ajax_add_message()
    {
        global $wpdb;

        if (!gd_client_portal_verify_request()) {
            wp_send_json_error(__('Please log in to continue.', 'gd-client-portal'));
        }

        if (!gd_client_portal_verify_nonce_request('gd_client_portal_project_message')) {
            wp_send_json_error(__('Invalid request.', 'gd-client-portal'));
        }

        $project_id = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (!$project_id || empty($message)) {
            wp_send_json_error(__('Please provide a message.', 'gd-client-portal'));
        }

        $table = $wpdb->prefix . 'gd_project_messages';
        $file_url = '';
        if (!empty($_FILES['message_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload($_FILES['message_file'], array('test_form' => false));
            if (!empty($upload['url'])) {
                $file_url = $upload['url'];
            }
        }

        $wpdb->insert(
            $table,
            array(
                'project_id' => $project_id,
                'user_id' => get_current_user_id(),
                'message' => $message,
                'file_url' => $file_url,
            ),
            array('%d', '%d', '%s', '%s')
        );

        // Notify project owner and admin by email
        $proj_table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $proj_table WHERE id = %d", $project_id));
        if ($project) {
            if (!gd_client_portal_verify_tenant_access(isset($project->tenant_id) ? $project->tenant_id : 0)) {
                wp_send_json_error(__('You do not have permission to post to this project.', 'gd-client-portal'));
            }

            $owner = get_userdata($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $author = get_userdata(get_current_user_id());
            $author_name = $author ? $author->display_name : __('Client', 'gd-client-portal');
            $subject = sprintf(__('New message on project: %s', 'gd-client-portal'), $project->title);
            $body = sprintf("%s\n\n%s: %s\n\n%s\n\n%s: %s", __('A new message was posted on your project.', 'gd-client-portal'), __('Project', 'gd-client-portal'), $project->title, $message, __('From', 'gd-client-portal'), $author_name);
            if (!empty($file_url)) {
                $body .= "\n\n" . __('Attachment:', 'gd-client-portal') . ' ' . $file_url;
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

        if (!gd_client_portal_verify_tenant_access(isset($project->tenant_id) ? $project->tenant_id : 0)) {
            wp_send_json_error(__('You do not have permission to update this project.', 'gd-client-portal'));
        }

        // Only owner can update unless tenant admin
        if (intval($project->user_id) !== get_current_user_id() && !gd_client_portal_user_is_tenant_admin()) {
            wp_send_json_error(__('You do not have permission to update this project.', 'gd-client-portal'));
        }

        $progress = gd_client_portal_auto_progress($project->service_type, $new_stage);
        gd_client_portal_update_project_stage($project_id, $new_stage, $progress);
        // Notify project owner/admin of stage update
        $proj_table = gd_client_portal_get_project_table_name();
        $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $proj_table WHERE id = %d", $project_id));
        if ($project) {
            $owner = get_userdata($project->user_id);
            $to = $owner ? $owner->user_email : get_option('admin_email');
            $subject = sprintf(__('Project stage updated: %s', 'gd-client-portal'), $project->title);
            $body = sprintf("%s\n\n%s: %s\n%s: %s", __('The project stage has been updated.', 'gd-client-portal'), __('Project', 'gd-client-portal'), $project->title, __('New Stage', 'gd-client-portal'), $new_stage);
            wp_mail($to, $subject, $body);
            wp_mail(get_option('admin_email'), $subject, $body);
        }

        wp_send_json_success(array('message' => __('Project stage updated.', 'gd-client-portal'), 'progress' => $progress));
    }
}

add_action('wp_ajax_gd_client_portal_add_message', 'gd_client_portal_ajax_add_message');
add_action('wp_ajax_nopriv_gd_client_portal_add_message', 'gd_client_portal_ajax_add_message');
add_action('wp_ajax_gd_client_portal_update_stage', 'gd_client_portal_ajax_update_stage');
add_action('wp_ajax_nopriv_gd_client_portal_update_stage', 'gd_client_portal_ajax_update_stage');

if (!function_exists('gd_client_portal_render_projects_list')) {
    function gd_client_portal_render_projects_list($atts = array())
    {
        if (!gd_client_portal_verify_request()) {
            return gd_client_portal_render_placeholder_content(__('Please log in to view your projects.', 'gd-client-portal'));
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table = gd_client_portal_get_project_table_name();

        $projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC", $user_id));

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
