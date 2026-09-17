<?php
/**
 * GD Client Portal v2.0 - Operations Command Center.
 * Aggregates operational queues across projects, orders, approvals and deliveries.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('gd_client_portal_operations_scope_project_where')) {
    function gd_client_portal_operations_scope_project_where(&$params) {
        if (current_user_can('manage_options')) return '';
        $tenant_id = absint(gd_client_portal_get_current_tenant_id());
        if ($tenant_id <= 0) return ' AND 1=0';
        $params[] = $tenant_id;
        return ' AND tenant_id = %d';
    }
}

if (!function_exists('gd_client_portal_get_operations_projects')) {
    function gd_client_portal_get_operations_projects($mode = 'all', $limit = 10) {
        global $wpdb;
        $params = array();
        $where = gd_client_portal_operations_scope_project_where($params);
        $table = gd_client_portal_get_project_table_name();
        switch ($mode) {
            case 'requirements': $where .= " AND current_stage IN ('intake','requirements','requirements_collection','awaiting_requirements','discovery','briefing') AND status NOT IN ('completed','cancelled')"; break;
            case 'approval': $where .= " AND current_stage IN ('ready_for_review','approval','client_review') AND status NOT IN ('completed','cancelled')"; break;
            case 'revisions': $where .= " AND current_stage IN ('revision','revisions','revision_requested') AND status NOT IN ('completed','cancelled')"; break;
            case 'overdue': $where .= " AND status NOT IN ('completed','cancelled') AND created_at < %s"; $params[] = gmdate('Y-m-d H:i:s', time() - (14 * DAY_IN_SECONDS)); break;
            case 'completed': $where .= " AND status IN ('completed','complete','closed')"; break;
            case 'active': $where .= " AND status NOT IN ('completed','cancelled','closed')"; break;
        }
        $params[] = max(1, min(50, absint($limit)));
        $sql = "SELECT * FROM {$table} WHERE 1=1 {$where} ORDER BY created_at DESC LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}

if (!function_exists('gd_client_portal_get_operations_orders')) {
    function gd_client_portal_get_operations_orders($limit = 10) {
        $args = array('post_type'=>'gd_client_portal_marketplace_order','post_status'=>'any','posts_per_page'=>max(1,min(50,absint($limit))),'orderby'=>'date','order'=>'DESC');
        if (!current_user_can('manage_options')) {
            $tenant_id = absint(gd_client_portal_get_current_tenant_id());
            if ($tenant_id <= 0) return array();
            $args['meta_query'] = array(array('key'=>'_gd_mp_order_tenant_id','value'=>(string)$tenant_id,'compare'=>'='));
        }
        $q = new WP_Query($args);
        return $q->posts;
    }
}

if (!function_exists('gd_client_portal_get_operations_counts')) {
    function gd_client_portal_get_operations_counts() {
        $counts = array();
        foreach (array('requirements','approval','revisions','overdue','at_risk','completed','active') as $mode) {
            $counts[$mode] = count(gd_client_portal_get_operations_projects($mode, 100));
        }
        $counts['orders'] = count(gd_client_portal_get_operations_orders(100));
        $counts['at_risk'] = function_exists('gd_client_portal_get_sla_projects') ? count(gd_client_portal_get_sla_projects('at_risk', 100)) : 0;
        $counts['notifications'] = function_exists('gd_client_portal_get_unread_notification_count') ? gd_client_portal_get_unread_notification_count() : 0;
        return $counts;
    }
}

if (!function_exists('gd_client_portal_operations_project_url')) {
    function gd_client_portal_operations_project_url($project_id) {
        return add_query_arg('project_id', absint($project_id), gd_client_portal_get_dashboard_url());
    }
}



/* v2.1 operational actions */
if (!function_exists('gd_client_portal_operations_action_ajax')) {
    function gd_client_portal_operations_action_ajax() {
        gd_client_portal_ajax_guard('gd_client_portal_operations','_wpnonce');
        $project_id = absint($_POST['project_id'] ?? 0);
        $action = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
        $project = gd_client_portal_get_project_by_id($project_id);
        if (!$project || !gd_client_portal_verify_project_access($project) || !(current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin())) {
            wp_send_json_error(__('You do not have permission to perform this operational action.', 'gd-client-portal'));
        }
        $owner = $project->user_id ? gd_client_portal_cached_user($project->user_id) : false;
        if ($action === 'notify_client') {
            if (!$owner) wp_send_json_error(__('This project has no assigned client.', 'gd-client-portal'));
            $subject = sprintf(__('Action needed: %s', 'gd-client-portal'), $project->title);
            $body = sprintf(__('Your project “%s” needs your attention. Please open your Client Portal workspace to continue.', 'gd-client-portal'), $project->title);
            wp_mail($owner->user_email, $subject, $body);
            if (function_exists('gd_client_portal_create_notification')) gd_client_portal_create_notification($owner->ID, __('Action needed on your project', 'gd-client-portal'), sprintf(__('Please review and continue %s in your Client Portal.', 'gd-client-portal'), $project->title), 'action_required', $project->id, $project->tenant_id);
            do_action('gd_client_portal_client_reminder_sent', $project_id);
            wp_send_json_success(array('message'=>__('Client action reminder sent.', 'gd-client-portal')));
        }
        if ($action === 'advance_stage') {
            $workflow = gd_client_portal_get_workflow($project->service_type);
            $index = array_search($project->current_stage, $workflow, true);
            if ($index === false || empty($workflow[$index + 1])) wp_send_json_error(__('This project is already at the final workflow stage.', 'gd-client-portal'));
            $next = $workflow[$index + 1];
            if (!gd_client_portal_update_project_stage($project_id, $next, gd_client_portal_auto_progress($project->service_type, $next))) wp_send_json_error(__('Unable to advance the project stage.', 'gd-client-portal'));
            wp_send_json_success(array('message'=>sprintf(__('Project advanced to %s.', 'gd-client-portal'), ucwords(str_replace('_',' ',$next))), 'stage'=>$next));
        }
        if ($action === 'start_revision') {
            $workflow = gd_client_portal_get_workflow($project->service_type);
            $target = in_array('revisions',$workflow,true) ? 'revisions' : $project->current_stage;
            if ($target === $project->current_stage || !gd_client_portal_update_project_stage($project_id, $target, gd_client_portal_auto_progress($project->service_type, $target))) wp_send_json_error(__('Unable to move this project into revision work.', 'gd-client-portal'));
            wp_send_json_success(array('message'=>__('Revision work has been started.', 'gd-client-portal'), 'stage'=>$target));
        }
        wp_send_json_error(__('Unknown operational action.', 'gd-client-portal'));
    }
    add_action('wp_ajax_gd_client_portal_operations_action', 'gd_client_portal_operations_action_ajax');
}

if (!function_exists('gd_client_portal_render_operations_command_center')) {
    function gd_client_portal_render_operations_command_center() {
        if (!gd_client_portal_verify_request() || !gd_client_portal_user_can_access_admin()) {
            return gd_client_portal_render_access_gate(__('Operations Command Center', 'gd-client-portal'), __('You do not have access to the portal operations workspace.', 'gd-client-portal'));
        }
        $counts = gd_client_portal_get_operations_counts();
        $action_nonce = wp_create_nonce('gd_client_portal_operations');
        $queues = array(
            'requirements' => array(__('Requirements awaiting action','gd-client-portal'), __('Clients/projects still need intake information or requirements review.','gd-client-portal')),
            'approval' => array(__('Deliverables awaiting approval','gd-client-portal'), __('Published work is ready for client review.','gd-client-portal')),
            'revisions' => array(__('Revision requests','gd-client-portal'), __('Clients have requested changes before final approval.','gd-client-portal')),
            'overdue' => array(__('Overdue projects','gd-client-portal'), __('Projects past their SLA deadline.','gd-client-portal')),
            'at_risk' => array(__('At-risk projects','gd-client-portal'), __('Deadlines approaching within the warning window.','gd-client-portal')),
        );
        ob_start();
        wp_enqueue_style('gd-client-portal-operations');
        wp_enqueue_script('gd-client-portal-operations');
        echo '<div class="gd-operations-center">';
        echo '<div class="gd-operations-hero"><div><span class="gd-operations-eyebrow">GD CLIENT PORTAL · V2.0</span><h2>'.esc_html__('Operations Command Center','gd-client-portal').'</h2><p>'.esc_html__('A single control room for projects, approvals, delivery, commerce and client activity.','gd-client-portal').'</p></div><div class="gd-operations-date">'.esc_html(wp_date(get_option('date_format'))).'</div></div>';
        echo '<div class="gd-operations-kpis">';
        $kpis = array(
            array('active',__('Active Projects','gd-client-portal'),__('In delivery','gd-client-portal')),
            array('requirements',__('Requirements','gd-client-portal'),__('Need attention','gd-client-portal')),
            array('approval',__('Awaiting Approval','gd-client-portal'),__('Client review','gd-client-portal')),
            array('revisions',__('Revisions','gd-client-portal'),__('Needs changes','gd-client-portal')),
            array('overdue',__('Overdue','gd-client-portal'),__('Past SLA deadline','gd-client-portal')),
            array('at_risk',__('At Risk','gd-client-portal'),__('Deadline approaching','gd-client-portal')),
            array('orders',__('Recent Orders','gd-client-portal'),__('Portal purchases','gd-client-portal')),
        );
        foreach ($kpis as $k) echo '<div class="gd-operations-kpi"><span>'.esc_html($k[1]).'</span><strong>'.intval($counts[$k[0]]).'</strong><small>'.esc_html($k[2]).'</small></div>';
        echo '</div>';
        echo '<div class="gd-operations-layout">';
        echo '<section class="gd-operations-main"><div class="gd-operations-section-head"><div><span class="gd-operations-label">ACTION QUEUES</span><h3>'.esc_html__('What needs attention','gd-client-portal').'</h3></div><a href="'.esc_url(admin_url('admin.php?page=gd-client-portal')).'">'.esc_html__('Portal admin','gd-client-portal').' →</a></div>';
        echo '<div class="gd-operations-queues">';
        foreach ($queues as $mode=>$data) {
            $items = in_array($mode, array('overdue','at_risk'), true) && function_exists('gd_client_portal_get_sla_projects') ? gd_client_portal_get_sla_projects($mode, 5) : gd_client_portal_get_operations_projects($mode, 5);
            echo '<article class="gd-operations-queue gd-queue-'.esc_attr($mode).'">';
            echo '<div class="gd-queue-head"><div><span class="gd-queue-count">'.intval($counts[$mode]).'</span><h4>'.esc_html($data[0]).'</h4><p>'.esc_html($data[1]).'</p></div></div>';
            if ($items) {
                echo '<div class="gd-operations-items">';
                foreach ($items as $project) {
                    $owner = $project->user_id ? gd_client_portal_cached_user($project->user_id) : false;
                    $owner_name = $owner ? $owner->display_name : __('Unassigned','gd-client-portal');
                    $sla = function_exists('gd_client_portal_ensure_project_sla') ? gd_client_portal_ensure_project_sla($project) : false;
                    $sla_label = $sla ? gd_client_portal_sla_remaining_label($sla->stage_due_date ?: $sla->due_date) : '';
                    $priority_label = $sla ? ucfirst($sla->priority) : '';
                    echo '<div class="gd-operation-item"><span class="gd-operation-icon">▣</span><span><strong>' . esc_html($project->title) . '</strong><small>' . esc_html($owner_name) . ' · ' . esc_html(ucwords(str_replace('_',' ',$project->current_stage ?: $project->status))) . '</small>' . ($sla ? '<small class="gd-sla-meta">'.esc_html($priority_label).' · '.esc_html($sla_label).'</small>' : '') . '<span class="gd-operation-actions">';
                    echo '<a class="gd-operation-open" href="' . esc_url(gd_client_portal_operations_project_url($project->id)) . '">'.esc_html__('Open','gd-client-portal').'</a>';
                    $assigned = function_exists('gd_client_portal_get_project_assignments') ? gd_client_portal_get_project_assignments($project->id) : array();
                    $lead = $assigned ? gd_client_portal_cached_user($assigned[0]->user_id) : false;
                    if ($lead) echo '<span class="gd-assignee-current">'.esc_html__('Lead:','gd-client-portal').' '.esc_html($lead->display_name).'</span>';
                    if (current_user_can('manage_options') || gd_client_portal_user_is_tenant_admin()) {
                        if (function_exists('gd_client_portal_sla_set')) {
                            echo '<span class="gd-sla-controls"><select class="gd-sla-priority"><option value="urgent">Urgent</option><option value="high">High</option><option value="normal" selected>Normal</option><option value="low">Low</option></select><input type="date" class="gd-sla-date" value="'.esc_attr($sla && $sla->due_date ? wp_date('Y-m-d', strtotime($sla->due_date)) : '').'"><button type="button" class="gd-sla-action" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($action_nonce).'">'.esc_html__('Save SLA','gd-client-portal').'</button></span>';
                        }

                        $team = function_exists('gd_client_portal_get_assignable_users') ? gd_client_portal_get_assignable_users($project->tenant_id) : array();
                        if ($team) {
                            echo '<span class="gd-assignment-controls"><select class="gd-assignment-user"><option value="">'.esc_html__('Assign team member…','gd-client-portal').'</option>';
                            foreach ($team as $member) echo '<option value="'.intval($member->ID).'">'.esc_html($member->display_name).'</option>';
                            echo '</select><button type="button" class="gd-assignment-action" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($action_nonce).'" data-role="lead">'.esc_html__('Assign lead','gd-client-portal').'</button></span>';
                        }
                    }
                    if (in_array($mode, array('requirements','overdue'), true)) echo '<button type="button" class="gd-operation-action" data-operation="notify_client" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($action_nonce).'">'.esc_html__('Remind client','gd-client-portal').'</button>';
                    if ($mode === 'revisions') echo '<button type="button" class="gd-operation-action" data-operation="start_revision" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($action_nonce).'">'.esc_html__('Start revision','gd-client-portal').'</button>';
                    if ($mode === 'active') echo '<button type="button" class="gd-operation-action" data-operation="advance_stage" data-project-id="'.intval($project->id).'" data-nonce="'.esc_attr($action_nonce).'">'.esc_html__('Advance','gd-client-portal').'</button>';
                    echo '</span></span><b>→</b></div>';
                }
                echo '</div>';
            } else echo '<div class="gd-operations-empty">'.esc_html__('Nothing currently requires action.', 'gd-client-portal').'</div>';
            echo '</article>';
        }
        echo '</div></section>';
        echo '<aside class="gd-operations-side">';
        echo '<section class="gd-operations-panel"><div class="gd-operations-section-head"><div><span class="gd-operations-label">PIPELINE</span><h3>'.esc_html__('Portfolio health','gd-client-portal').'</h3></div></div><div class="gd-pipeline-row"><span>'.esc_html__('Active projects','gd-client-portal').'</span><strong>'.intval($counts['active']).'</strong></div><div class="gd-pipeline-row"><span>'.esc_html__('Completed projects','gd-client-portal').'</span><strong>'.intval($counts['completed']).'</strong></div><div class="gd-pipeline-row"><span>'.esc_html__('Unread notifications','gd-client-portal').'</span><strong>'.intval($counts['notifications']).'</strong></div></section>';
        echo '<section class="gd-operations-panel"><div class="gd-operations-section-head"><div><span class="gd-operations-label">TEAM</span><h3>'.esc_html__('Workload','gd-client-portal').'</h3></div></div>';
        if (function_exists('gd_client_portal_get_user_workload')) {
            $workload_tenant = current_user_can('manage_options') ? absint(get_option('gd_client_portal_default_tenant_id',0)) : absint(gd_client_portal_get_current_tenant_id());
            $workload = $workload_tenant ? gd_client_portal_get_user_workload($workload_tenant,8) : array();
            if ($workload) foreach ($workload as $w) { $name=$w->user ? $w->user->display_name : __('Team member','gd-client-portal'); echo '<div class="gd-workload-row"><div><strong>'.esc_html($name).'</strong><small>'.intval($w->active_count).' active · '.intval($w->attention_count).' attention</small></div><b>'.intval($w->active_count).'</b></div>'; }
            else echo '<div class="gd-operations-empty">'.esc_html__('No assigned workload yet.','gd-client-portal').'</div>';
        }
        echo '</section>';
        echo '<section class="gd-operations-panel"><div class="gd-operations-section-head"><div><span class="gd-operations-label">TEAM</span><h3>'.esc_html__('Workload','gd-client-portal').'</h3></div></div>';
        if (function_exists('gd_client_portal_get_user_workload')) {
            $workload_tenant = current_user_can('manage_options') ? absint(get_option('gd_client_portal_default_tenant_id',0)) : absint(gd_client_portal_get_current_tenant_id());
            $workload = $workload_tenant ? gd_client_portal_get_user_workload($workload_tenant,8) : array();
            if ($workload) foreach ($workload as $w) { $name=$w->user ? $w->user->display_name : __('Team member','gd-client-portal'); echo '<div class="gd-workload-row"><div><strong>'.esc_html($name).'</strong><small>'.intval($w->active_count).' active · '.intval($w->attention_count).' attention</small></div><b>'.intval($w->active_count).'</b></div>'; }
            else echo '<div class="gd-operations-empty">'.esc_html__('No assigned workload yet.','gd-client-portal').'</div>';
        }
        echo '</section>';
        echo '<section class="gd-operations-panel"><div class="gd-operations-section-head"><div><span class="gd-operations-label">RECENT ORDERS</span><h3>'.esc_html__('Commerce activity','gd-client-portal').'</h3></div></div>';
        $orders = gd_client_portal_get_operations_orders(6);
        if ($orders) foreach ($orders as $order) {
            $status = get_post_status($order->ID); $customer = get_post_meta($order->ID,'_gd_mp_order_customer_name',true); if (!$customer) $customer = get_post_meta($order->ID,'_gd_mp_order_email',true);
            echo '<div class="gd-order-row"><span>#'.intval($order->ID).'</span><div><strong>'.esc_html($customer ?: __('Customer','gd-client-portal')).'</strong><small>'.esc_html(ucwords(str_replace(array('wc-','_','-'),' ',(string)$status))).'</small></div><time>'.esc_html(get_the_date('', $order)).'</time></div>';
        } else echo '<div class="gd-operations-empty">'.esc_html__('No recent portal orders found.', 'gd-client-portal').'</div>';
        echo '</section></aside></div>';
        echo '<div class="gd-operations-footer"><span>'.esc_html__('Operations Command Center keeps the team focused on the next action rather than searching across modules.','gd-client-portal').'</span><a href="'.esc_url(gd_client_portal_get_dashboard_url()).'">'.esc_html__('Open client portal','gd-client-portal').' →</a></div>';
        echo '</div>';
        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_register_operations_assets')) {
    function gd_client_portal_register_operations_assets() {
        wp_register_style('gd-client-portal-operations', GD_CLIENT_PORTAL_URL.'assets/operations.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
        wp_register_script('gd-client-portal-operations', GD_CLIENT_PORTAL_URL.'assets/operations.js', array(), GD_CLIENT_PORTAL_VERSION, true);
    }
    add_action('wp_enqueue_scripts','gd_client_portal_register_operations_assets');
    add_action('admin_enqueue_scripts','gd_client_portal_register_operations_assets');
}

if (!function_exists('gd_client_portal_register_operations_menu')) {
    function gd_client_portal_register_operations_menu() {
        add_submenu_page('gd-client-portal', __('Operations Center','gd-client-portal'), __('Operations Center','gd-client-portal'), 'gd_client_portal_access_admin', 'gd-client-portal-operations', 'gd_client_portal_render_operations_admin_page');
    }
    add_action('admin_menu','gd_client_portal_register_operations_menu',20);
}

if (!function_exists('gd_client_portal_render_operations_admin_page')) {
    function gd_client_portal_render_operations_admin_page() {
        if (!gd_client_portal_user_can_access_admin()) wp_die(esc_html__('You do not have permission to access this page.','gd-client-portal'));
        echo '<div class="wrap gd-operations-admin-wrap">'.gd_client_portal_render_operations_command_center().'</div>';
    }
}
add_shortcode('gd_operations_command_center','gd_client_portal_render_operations_command_center');
