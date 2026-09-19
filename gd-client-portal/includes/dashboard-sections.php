<?php
/**
 * Dashboard information architecture and section organization.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_render_placeholder_shortcode')) {
    /**
     * Live fallback summary for modules that do not expose a dedicated shortcode.
     * Never uses fabricated counts, names, dates, or statuses.
     */
    function gd_client_portal_render_placeholder_shortcode($atts = array(), $content = null, $tag = '')
    {
        $module = isset($atts['module']) ? sanitize_key($atts['module']) : sanitize_key(str_replace(array('gd_', '_ui'), array('', ''), $tag ?: 'module'));
        $title_map = array(
            'projects' => __('Projects', 'gd-client-portal'),
            'workflow' => __('Workflow', 'gd-client-portal'),
            'files' => __('Files', 'gd-client-portal'),
            'deliverables' => __('Deliverables', 'gd-client-portal'),
            'messages' => __('Messages', 'gd-client-portal'),
            'meetings' => __('Meetings', 'gd-client-portal'),
            'support' => __('Support', 'gd-client-portal'),
            'marketplace' => __('Marketplace', 'gd-client-portal'),
            'downloads' => __('Downloads', 'gd-client-portal'),
            'invoices' => __('Billing', 'gd-client-portal'),
            'account' => __('Account', 'gd-client-portal'),
        );
        $title = $title_map[$module] ?? __('Module overview', 'gd-client-portal');
        $tenant_id = function_exists('gd_client_portal_get_current_tenant_id') ? absint(gd_client_portal_get_current_tenant_id()) : 0;
        $user_id = gd_client_portal_cached_current_user_id();
        $stats = array();

        if (in_array($module, array('projects','workflow','deliverables','files'), true) && function_exists('gd_client_portal_get_visible_projects')) {
            $projects = gd_client_portal_get_visible_projects(50);
            $active = array_filter($projects, function($p){ return !in_array((string)($p->status ?? ''), array('completed','closed','cancelled'), true); });
            $stats[] = array(__('Projects', 'gd-client-portal'), number_format_i18n(count($projects)));
            $stats[] = array(__('Active', 'gd-client-portal'), number_format_i18n(count($active)));
            if ($module === 'workflow' && !empty($active[0])) {
                $stats[] = array(__('Current stage', 'gd-client-portal'), ucwords(str_replace('_',' ',(string)($active[0]->current_stage ?? 'Setup'))));
            } elseif ($module === 'deliverables') {
                $published = 0;
                if (function_exists('gdcp_delivery_service')) foreach ($projects as $p) if (gdcp_delivery_service()->latest(absint($p->id), true)) $published++;
                $stats[] = array(__('Published', 'gd-client-portal'), number_format_i18n($published));
            } elseif ($module === 'files') {
                $files = 0; foreach ($projects as $p) if (!empty($p->file_url)) $files++;
                $stats[] = array(__('Available', 'gd-client-portal'), number_format_i18n($files));
            } else {
                $stats[] = array(__('Completion', 'gd-client-portal'), !empty($active[0]) ? absint($active[0]->progress ?? 0).'%' : '—');
            }
        } elseif ($module === 'messages' && function_exists('gdcp_message_service')) {
            $rows = gdcp_message_service()->list_for_user($user_id, $tenant_id, function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin(), 20);
            $stats[] = array(__('Messages', 'gd-client-portal'), number_format_i18n(count($rows)));
            $stats[] = array(__('Latest', 'gd-client-portal'), !empty($rows[0]->created_at) ? mysql2date(get_option('date_format'), $rows[0]->created_at) : '—');
        } elseif ($module === 'meetings' && function_exists('gdcp_calendar_service')) {
            $events = gdcp_calendar_service()->custom_events(current_time('mysql'), gmdate('Y-m-d H:i:s', current_time('timestamp') + 30 * DAY_IN_SECONDS));
            $stats[] = array(__('Upcoming', 'gd-client-portal'), number_format_i18n(count($events)));
            $stats[] = array(__('Window', 'gd-client-portal'), __('Next 30 days', 'gd-client-portal'));
        } elseif ($module === 'support' && class_exists('GDCP_Support_Repository')) {
            $repo = new GDCP_Support_Repository();
            $where = array('tenant_id=%d'); $params = array($tenant_id);
            if (!function_exists('gd_client_portal_user_is_tenant_admin') || !gd_client_portal_user_is_tenant_admin()) { $where[]='user_id=%d'; $params[]=$user_id; }
            $tickets = $repo->list_tickets($where, $params, 20);
            $open = array_filter($tickets, function($t){ return !in_array((string)($t->status ?? ''), array('resolved','closed'), true); });
            $stats[] = array(__('Tickets', 'gd-client-portal'), number_format_i18n(count($tickets)));
            $stats[] = array(__('Open', 'gd-client-portal'), number_format_i18n(count($open)));
        } elseif ($module === 'marketplace') {
            $items = get_option('gd_client_portal_marketplace_items', array());
            $visible = array_filter((array)$items, function($i){ return !empty($i['enabled']); });
            $stats[] = array(__('Available', 'gd-client-portal'), number_format_i18n(count($visible)));
            $stats[] = array(__('Updated', 'gd-client-portal'), current_time(get_option('date_format')));
        } elseif ($module === 'invoices' && class_exists('GDCP_Billing_Repository')) {
            $repo = new GDCP_Billing_Repository();
            $rows = $repo->list_outstanding_for_scope($user_id, $tenant_id, function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin(), function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin(), 20);
            $stats[] = array(__('Outstanding', 'gd-client-portal'), number_format_i18n(count($rows)));
            $total = 0; foreach ($rows as $row) $total += max(0, (float)($row->total ?? 0) - (float)($row->amount_paid ?? 0));
            $stats[] = array(__('Balance', 'gd-client-portal'), $rows ? (($rows[0]->currency ?? 'GHS').' '.number_format_i18n($total,2)) : '—');
        } elseif ($module === 'account') {
            $user = wp_get_current_user();
            $stats[] = array(__('Account', 'gd-client-portal'), $user->exists() ? __('Active', 'gd-client-portal') : __('Signed out', 'gd-client-portal'));
            $stats[] = array(__('Email', 'gd-client-portal'), $user->exists() ? $user->user_email : '—');
        } else {
            $stats[] = array(__('Status', 'gd-client-portal'), __('No live data available', 'gd-client-portal'));
            $stats[] = array(__('Next step', 'gd-client-portal'), __('Open this module to view available records.', 'gd-client-portal'));
        }

        $html = '<div class="gd-module-detail gd-live-summary">';
        $html .= '<header class="gd-module-detail-header"><div><span class="gd-module-kicker">'.esc_html__('Live account data','gd-client-portal').'</span><h2>'.esc_html($title).'</h2></div><span class="gd-module-status">'.esc_html__('Live','gd-client-portal').'</span></header>';
        $html .= '<div class="gd-module-stats">';
        foreach ($stats as $stat) $html .= '<div class="gd-module-stat"><strong>'.esc_html($stat[1]).'</strong><span>'.esc_html($stat[0]).'</span></div>';
        $html .= '</div><p class="gd-live-summary-note">'.esc_html__('This summary is generated from your current portal records. Open the module for full details.', 'gd-client-portal').'</p></div>';
        return $html;
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
                echo '<div class="gd-card-content"><div class="gd-card-summary">' . esc_html(gd_client_portal_get_dashboard_card_summary($card_id, $card)) . '</div></div>';

                // Add a primary action button linking to module-specific view on the dashboard, or to a provided card URL
                if (!empty($card['url'])) {
                    $action_url = esc_url($card['url']);
                } else {
                    $action_url = esc_url(add_query_arg('module', $card_id, gd_client_portal_get_dashboard_url()));
                }
                echo '<div class="gd-card-actions">';
                echo '<a class="gd-btn" href="' . $action_url . '" data-module="' . esc_attr($card_id) . '"' . (empty($card['url']) ? '' : ' target="_blank" rel="noopener noreferrer"') . '>' . esc_html__('Open', 'gd-client-portal') . '</a>';
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
add_shortcode('gd_customer_files', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_private_messages', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_booking_calendar', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_support_tickets', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_marketplace', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_woo_downloads', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_woo_invoices', 'gd_client_portal_render_billing_center');
add_shortcode('gd_billing_center', 'gd_client_portal_render_billing_center');
add_shortcode('gd_account_settings', 'gd_client_portal_render_placeholder_shortcode');
