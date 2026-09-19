if (!function_exists('gd_client_portal_render_placeholder_shortcode')) {
    function gd_client_portal_render_placeholder_shortcode($atts = array(), $content = null, $tag = '')
    {
        $module = isset($atts['module']) ? sanitize_key($atts['module']) : sanitize_key(str_replace(array('gd_', '_ui'), array('', ''), $tag ?: 'module'));
        $renderer = function_exists('gd_client_portal_get_registered_dashboard_view') ? gd_client_portal_get_registered_dashboard_view($module) : null;
        if ($renderer) {
            try {
                $output = is_callable($renderer) ? call_user_func($renderer, $atts) : '';
                if (is_string($output) && trim(strip_tags($output)) !== '') return $output;
            } catch (Throwable $e) {}
        }
        $fallbacks = array(
            'projects' => __('No project workspace is available yet.', 'gd-client-portal'),
            'workflow' => __('No workflow activity is available yet.', 'gd-client-portal'),
            'files' => __('No shared files are available yet.', 'gd-client-portal'),
            'deliverables' => __('No deliverables are available yet.', 'gd-client-portal'),
            'messages' => __('No project messages are available yet.', 'gd-client-portal'),
            'meetings' => __('No upcoming meetings are scheduled.', 'gd-client-portal'),
            'support' => __('No support activity is available yet.', 'gd-client-portal'),
            'marketplace' => __('No marketplace items are currently available.', 'gd-client-portal'),
            'downloads' => __('No downloadable resources are currently available.', 'gd-client-portal'),
            'account' => __('Your account details are ready to review.', 'gd-client-portal'),
        );
        return '<div class="gd-module-empty-state"><p>' . esc_html(isset($fallbacks[$module]) ? $fallbacks[$module] : __('No content is available for this module yet.', 'gd-client-portal')) . '</p></div>';
    }
}

if (!function_exists('gd_client_portal_get_dashboard_card_live_data')) {
    function gd_client_portal_get_dashboard_card_live_data($card_id)
    {
        $data = array('summary' => '', 'status' => '');
        switch ($card_id) {
            case 'projects':
                $projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(500) : array();
                $count = count((array) $projects);
                $data['summary'] = $count ? sprintf(_n('%d project currently visible to you.', '%d projects currently visible to you.', $count, 'gd-client-portal'), $count) : __('No projects are currently assigned to you.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d active', '%d active', $count, 'gd-client-portal'), $count) : __('No active projects', 'gd-client-portal');
                break;
            case 'workflow':
                $projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(500) : array();
                $tasks = array();
                foreach ((array) $projects as $project) if (function_exists('gd_client_portal_collab_get_tasks')) $tasks = array_merge($tasks, (array) gd_client_portal_collab_get_tasks(absint($project->id), 100));
                $open = 0; $due = 0; $today = current_time('timestamp'); $week = strtotime('+7 days', $today);
                foreach ($tasks as $task) {
                    $status = isset($task->status) ? sanitize_key($task->status) : '';
                    if (!in_array($status, array('done', 'completed'), true)) $open++;
                    if (!empty($task->due_date) && strtotime($task->due_date) >= $today && strtotime($task->due_date) <= $week && !in_array($status, array('done', 'completed'), true)) $due++;
                }
                $data['summary'] = $open ? sprintf(_n('%d open workflow task across your projects.', '%d open workflow tasks across your projects.', $open, 'gd-client-portal'), $open) : __('No open workflow tasks across your projects.', 'gd-client-portal');
                $data['status'] = $due ? sprintf(_n('%d due this week', '%d due this week', $due, 'gd-client-portal'), $due) : __('No tasks due this week', 'gd-client-portal');
                break;
            case 'files':
                $rows = function_exists('gd_client_portal_documents_get') ? gd_client_portal_documents_get(array('limit' => 500)) : array();
                $count = count((array) $rows);
                $data['summary'] = $count ? sprintf(_n('%d shared file available in your workspace.', '%d shared files available in your workspace.', $count, 'gd-client-portal'), $count) : __('No shared files are available yet.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d file', '%d files', $count, 'gd-client-portal'), $count) : __('No files yet', 'gd-client-portal');
                break;
            case 'deliverables':
                $projects = function_exists('gd_client_portal_get_visible_projects') ? gd_client_portal_get_visible_projects(500) : array();
                $count = 0;
                foreach ((array) $projects as $project) if (function_exists('gd_client_portal_get_delivery_versions')) $count += count((array) gd_client_portal_get_delivery_versions(absint($project->id), true));
                $data['summary'] = $count ? sprintf(_n('%d published deliverable is available.', '%d published deliverables are available.', $count, 'gd-client-portal'), $count) : __('No published deliverables are available yet.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d published', '%d published', $count, 'gd-client-portal'), $count) : __('Nothing published yet', 'gd-client-portal');
                break;
            case 'messages':
                $rows = function_exists('gdcp_message_repository') ? gdcp_message_repository()->list_for_user(get_current_user_id(), gd_client_portal_get_current_tenant_id(), gd_client_portal_user_is_tenant_admin(), 20) : array();
                $count = count((array) $rows); $latest = !empty($rows) ? $rows[0] : null;
                $data['summary'] = $latest && !empty($latest->body) ? sprintf(__('Latest project message: %s', 'gd-client-portal'), wp_trim_words(wp_strip_all_tags($latest->body), 14)) : ($count ? sprintf(_n('%d project message available.', '%d project messages available.', $count, 'gd-client-portal'), $count) : __('No project messages yet.', 'gd-client-portal'));
                $data['status'] = $count ? sprintf(_n('%d recent message', '%d recent messages', $count, 'gd-client-portal'), $count) : __('No messages yet', 'gd-client-portal');
                break;
            case 'meetings':
                $start = current_time('mysql'); $end = date('Y-m-d H:i:s', strtotime('+30 days', current_time('timestamp')));
                $events = function_exists('gd_client_portal_calendar_events') ? gd_client_portal_calendar_events($start, $end, 0) : array();
                $count = count((array) $events); $next = !empty($events) ? $events[0] : null;
                $next_title = $next ? (is_array($next) ? ($next['title'] ?? '') : ($next->title ?? '')) : '';
                $data['summary'] = $next_title ? sprintf(__('Next scheduled event: %s', 'gd-client-portal'), $next_title) : __('No meetings or milestones are scheduled in the next 30 days.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d upcoming event', '%d upcoming events', $count, 'gd-client-portal'), $count) : __('No upcoming events', 'gd-client-portal');
                break;
            case 'support':
                $rows = function_exists('gd_client_portal_support_tickets') ? gd_client_portal_support_tickets(100) : array(); $open = 0;
                foreach ((array) $rows as $ticket) { $status = isset($ticket->status) ? sanitize_key($ticket->status) : ''; if (!in_array($status, array('closed', 'resolved'), true)) $open++; }
                $data['summary'] = $open ? sprintf(_n('%d support ticket needs attention.', '%d support tickets need attention.', $open, 'gd-client-portal'), $open) : __('No open support tickets need attention.', 'gd-client-portal');
                $data['status'] = $open ? sprintf(_n('%d open', '%d open', $open, 'gd-client-portal'), $open) : __('All clear', 'gd-client-portal');
                break;
            case 'marketplace':
                $items = function_exists('gd_client_portal_filter_items_by_tenant') ? gd_client_portal_filter_items_by_tenant(get_option('gd_client_portal_marketplace_items', array()), gd_client_portal_get_current_tenant_id()) : array();
                $visible = array_filter((array) $items, static function ($item) { return !empty($item['enabled']); }); $count = count($visible);
                $data['summary'] = $count ? sprintf(_n('%d service is currently available in the marketplace.', '%d services are currently available in the marketplace.', $count, 'gd-client-portal'), $count) : __('No marketplace services are currently available.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d available', '%d available', $count, 'gd-client-portal'), $count) : __('No services available', 'gd-client-portal');
                break;
            case 'downloads':
                $downloads = function_exists('wc_get_customer_available_downloads') ? wc_get_customer_available_downloads() : array(); $count = count((array) $downloads);
                $data['summary'] = $count ? sprintf(_n('%d downloadable resource is available to you.', '%d downloadable resources are available to you.', $count, 'gd-client-portal'), $count) : __('No downloadable resources are currently available.', 'gd-client-portal');
                $data['status'] = $count ? sprintf(_n('%d ready', '%d ready', $count, 'gd-client-portal'), $count) : __('No downloads yet', 'gd-client-portal');
                break;
            case 'invoices':
                $invoices = function_exists('gd_client_portal_billing_get_invoices') ? gd_client_portal_billing_get_invoices(200) : array(); $due = 0; $balance = 0.0;
                foreach ((array) $invoices as $invoice) { $status = isset($invoice->status) ? sanitize_key($invoice->status) : ''; if (!in_array($status, array('paid', 'cancelled', 'canceled'), true)) { $due++; $balance += max(0, (float) $invoice->total - (float) $invoice->amount_paid); } }
                $data['summary'] = $due ? sprintf(_n('%d invoice currently has an outstanding balance.', '%d invoices currently have outstanding balances.', $due, 'gd-client-portal'), $due) : __('No outstanding invoice balance.', 'gd-client-portal');
                $data['status'] = $due ? sprintf(_n('%d outstanding', '%d outstanding', $due, 'gd-client-portal'), $due) : __('Paid up to date', 'gd-client-portal');
                if ($due && $balance > 0 && function_exists('gd_client_portal_billing_money')) $data['summary'] .= ' ' . sprintf(__('Outstanding balance: %s.', 'gd-client-portal'), gd_client_portal_billing_money($balance, 'GHS'));
                break;
            case 'account':
                $user = wp_get_current_user(); $fields = array('first_name', 'last_name', 'billing_email', 'billing_phone', 'company_name', 'company_address'); $filled = 0;
                foreach ($fields as $field) if (get_user_meta($user->ID, $field, true) !== '') $filled++;
                $data['summary'] = $filled === count($fields) ? __('Your profile information is complete.', 'gd-client-portal') : sprintf(__('%d of %d key profile fields are filled in.', 'gd-client-portal'), $filled, count($fields));
                $data['status'] = $filled === count($fields) ? __('Profile complete', 'gd-client-portal') : __('Profile needs review', 'gd-client-portal');
                break;
        }
        return $data;
    }
}

if (!function_exists('gd_client_portal_get_dashboard_sections_for_current_user')) {
    function gd_client_portal_get_dashboard_sections_for_current_user()
    {
        $sections = gd_client_portal_get_dashboard_sections();
        if (current_user_can('manage_options')) {
            return $sections;
        }

        $tenant_id = gd_client_portal_get_current_tenant_id();
        if ($tenant_id <= 0) {
            return array();
        }

        return $sections;
    }
}

if (!function_exists('gd_client_portal_get_dashboard_sections')) {
    function gd_client_portal_get_dashboard_sections()
    {
        return array(
            'client_workspace' => array(
                'label' => __('Client Workspace', 'gd-client-portal'),
                'description' => __('Manage your projects and deliverables', 'gd-client-portal'),
                'icon' => '📋',
                'cards' => array(
                    array(
                        'id' => 'onboarding',
                        'label' => __('Service Intake', 'gd-client-portal'),
                        'shortcode' => '[gd_service_intake]',
                        'url' => home_url('/gd-client-portal-service-intake/'),
                        'icon' => '📝',
                        'description' => __('Complete the tailored intake form for the service you purchased', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'projects',
                        'label' => __('Projects', 'gd-client-portal'),
                        'shortcode' => '[gd_project_workspace]',
                        'url' => home_url('/gd-client-portal-projects/'),
                        'icon' => '📁',
                        'description' => __('Open a complete project workspace for requirements, workflow, files, deliverables, and messages', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'workflow',
                        'label' => __('Workflow', 'gd-client-portal'),
                        'shortcode' => '[gd_workflow_ui]',
                        'url' => home_url('/workflow-ui/'),
                        'icon' => '⚙️',
                        'description' => __('Track project progress through stages', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'files',
                        'label' => __('Files', 'gd-client-portal'),
                        'shortcode' => '[gd_customer_files]',
                        'url' => home_url('/files/'),
                        'icon' => '📄',
                        'description' => __('Access your project files securely', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'deliverables',
                        'label' => __('Deliverables', 'gd-client-portal'),
                        'shortcode' => '[gd_project_deliverables]',
                        'url' => home_url('/deliverables-2/'),
                        'icon' => '📦',
                        'description' => __('Review project deliverables', 'gd-client-portal'),
                    ),
                ),
            ),
            'collaboration' => array(
                'label' => __('Collaboration', 'gd-client-portal'),
                'description' => __('Communicate with your service team', 'gd-client-portal'),
                'icon' => '💬',
                'cards' => array(
                    array(
                        'id' => 'messages',
                        'label' => __('Messages', 'gd-client-portal'),
                        'shortcode' => '[gd_private_messages]',
                        'url' => home_url('/gd-client-portal-private-messages/'),
                        'icon' => '✉️',
                        'description' => __('Send and receive messages', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'meetings',
                        'label' => __('Meetings', 'gd-client-portal'),
                        'shortcode' => '[gd_booking_calendar]',
                        'url' => home_url('/meeting/'),
                        'icon' => '📅',
                        'description' => __('Schedule and manage meetings', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'support',
                        'label' => __('Support', 'gd-client-portal'),
                        'shortcode' => '[gd_support_tickets]',
                        'url' => home_url('/gd-client-portal-support-tickets/'),
                        'icon' => '🎟️',
                        'description' => __('Submit support tickets', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'feedback',
                        'label' => __('Feedback', 'gd-client-portal'),
                        'shortcode' => '[gd_feedback]',
                        'url' => home_url('/gd-client-portal-feedback/'),
                        'icon' => '⭐',
                        'description' => __('Rate completed projects and share service feedback', 'gd-client-portal'),
                    ),
                ),
            ),
            'commerce' => array(
                'label' => __('Commerce', 'gd-client-portal'),
                'description' => __('Manage purchases and downloads', 'gd-client-portal'),
                'icon' => '🛒',
                'cards' => array(
                    array(
                        'id' => 'marketplace',
                        'label' => __('Marketplace', 'gd-client-portal'),
                        'shortcode' => '[gd_marketplace]',
                        'url' => home_url('/marketplace/'),
                        'icon' => '🏪',
                        'description' => __('Browse and purchase services', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'downloads',
                        'label' => __('Downloads', 'gd-client-portal'),
                        'shortcode' => '[gd_woo_downloads]',
                        'url' => home_url('/gd-client-portal-woo-downloads/'),
                        'icon' => '⬇️',
                        'description' => __('Access your digital downloads', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'invoices',
                        'label' => __('Invoices', 'gd-client-portal'),
                        'shortcode' => '[gd_woo_invoices]',
                        'url' => home_url('/gd-client-portal-woo-invoice/'),
                        'icon' => '💰',
                        'description' => __('View your invoices and billing', 'gd-client-portal'),
                    ),
                ),
            ),
            'account' => array(
                'label' => __('Account', 'gd-client-portal'),
                'description' => __('Manage your profile and settings', 'gd-client-portal'),
                'icon' => '👤',
                'cards' => array(
                    array(
                        'id' => 'account',
                        'label' => __('Account Settings', 'gd-client-portal'),
                        'shortcode' => '[gd_account_settings]',
                        'url' => home_url('/profile/'),
                        'icon' => '⚙️',
                        'description' => __('Update your profile and preferences', 'gd-client-portal'),
                    ),
                ),
            ),
        );
    }
}

if (!function_exists('gd_client_portal_get_dashboard_card_summary')) {
    function gd_client_portal_get_dashboard_card_summary($card_id, $card = array())
    {
        $summaries = array(
            'projects' => __('Track project progress and upcoming deliverables.', 'gd-client-portal'),
            'workflow' => __('Follow active milestones and task progress.', 'gd-client-portal'),
            'files' => __('Access shared documents and project assets.', 'gd-client-portal'),
            'deliverables' => __('Review outputs, milestones, and handoff items.', 'gd-client-portal'),
            'messages' => __('Check recent updates and team communication.', 'gd-client-portal'),
            'meetings' => __('View upcoming sessions and meeting logistics.', 'gd-client-portal'),
            'support' => __('Manage tickets and service requests.', 'gd-client-portal'),
            'marketplace' => __('Browse available services and recent purchases.', 'gd-client-portal'),
            'downloads' => __('Open available digital downloads and files.', 'gd-client-portal'),
            'invoices' => __('Review balances, invoices, and billing activity.', 'gd-client-portal'),
            'account' => __('Manage profile details and account settings.', 'gd-client-portal'),
        );

        if (!empty($summaries[$card_id])) {
            return $summaries[$card_id];
        }

        if (!empty($card['description'])) {
            return $card['description'];
        }

        return __('Open this module for more details.', 'gd-client-portal');
    }
}

if (!function_exists('gd_client_portal_render_dashboard_sections')) {
    function gd_client_portal_render_dashboard_sections()
    {
        if (!gd_client_portal_verify_request()) {
            return gd_client_portal_render_access_gate();
        }

        $enable_sections = get_option('gd_client_portal_enable_sections', '1');

        if ($enable_sections !== '1') {
            return __('The dashboard sections layout is currently disabled.', 'gd-client-portal');
        }

        if (!current_user_can('manage_options')) {
            $tenant_id = gd_client_portal_get_current_tenant_id();
            if ($tenant_id <= 0) {
                return __('Your account is not assigned to a tenant yet. Please contact the portal administrator to complete your access setup.', 'gd-client-portal');
            }
        }

        $sections = gd_client_portal_get_dashboard_sections_for_current_user();
        if (empty($sections)) {
            return __('No dashboard sections are available for your current tenant.', 'gd-client-portal');
        }

        ob_start();

        echo '<div class="gd-dashboard-sections">';

        foreach ($sections as $section_id => $section) {
            echo '<section class="gd-dashboard-section" id="' . esc_attr($section_id) . '">';
            echo '<div class="gd-section-header">';
            echo '<h2>' . esc_html($section['icon']) . ' ' . esc_html($section['label']) . '</h2>';
            echo '<p class="gd-section-description">' . esc_html($section['description']) . '</p>';
            echo '</div>';

            echo '<div class="gd-cards-grid">';
            foreach ($section['cards'] as $card) {
                $card_id = isset($card['id']) ? sanitize_key($card['id']) : '';
                echo '<div class="gd-card" id="gd-card-' . esc_attr($card_id) . '" data-module="' . esc_attr($card_id) . '">';
                echo '<div class="gd-card-header">';
                echo '<span class="gd-card-icon">' . esc_html($card['icon']) . '</span>';
                echo '<h3>' . esc_html($card['label']) . '</h3>';
                echo '</div>';
                echo '<p class="gd-card-description">' . esc_html($card['description']) . '</p>';
                $live = gd_client_portal_get_dashboard_card_live_data($card_id);
                echo '<div class="gd-card-content"><div class="gd-card-summary">' . esc_html(!empty($live['summary']) ? $live['summary'] : gd_client_portal_get_dashboard_card_summary($card_id, $card)) . '</div>'; if (!empty($live['status'])) { echo '<div class="gd-card-status">' . esc_html($live['status']) . '</div>'; } echo '</div>';

                // Add a primary action button linking to module-specific view on the dashboard, or to a provided card URL
                $is_external = false;
                if (!empty($card['url'])) {
                    $action_url = esc_url($card['url']);
                    $target_host = wp_parse_url($card['url'], PHP_URL_HOST);
                    $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
                    $is_external = $target_host && $site_host && strtolower($target_host) !== strtolower($site_host);
                } else {
                    $action_url = esc_url(add_query_arg('module', $card_id, gd_client_portal_get_dashboard_url()));
                }
                echo '<div class="gd-card-actions">';
                echo '<a class="gd-btn" href="' . $action_url . '" data-module="' . esc_attr($card_id) . '"' . ($is_external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . esc_html__('Open', 'gd-client-portal') . '</a>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';

            echo '</section>';
        }

        echo '</div>';

        return ob_get_clean();
    }
}

add_shortcode('gd_dashboard_sections', 'gd_client_portal_render_dashboard_sections');
foreach (array(
    'gd_customer_files',
    'gd_private_messages',
    'gd_booking_calendar',
    'gd_support_tickets',
    'gd_marketplace',
    'gd_woo_downloads',
    'gd_account_settings',
) as $gdcp_legacy_shortcode) {
    if (!shortcode_exists($gdcp_legacy_shortcode)) {
        add_shortcode($gdcp_legacy_shortcode, 'gd_client_portal_render_placeholder_shortcode');
    }
}
if (!shortcode_exists('gd_woo_invoices')) add_shortcode('gd_woo_invoices', 'gd_client_portal_render_billing_center');
if (!shortcode_exists('gd_billing_center')) add_shortcode('gd_billing_center', 'gd_client_portal_render_billing_center');
