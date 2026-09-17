<?php
/**
 * Dashboard information architecture and section organization.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_render_placeholder_shortcode')) {
    function gd_client_portal_render_placeholder_shortcode($atts = array(), $content = null, $tag = '')
    {
        $module = isset($atts['module']) ? sanitize_key($atts['module']) : sanitize_key(str_replace(array('gd_', '_ui'), array('', ''), $tag ?: 'module'));

        $module_labels = array(
            'projects' => array('title' => __('Project overview', 'gd-client-portal'), 'subtitle' => __('Your current service portfolio', 'gd-client-portal'), 'status' => __('3 active projects', 'gd-client-portal')),
            'workflow' => array('title' => __('Workflow overview', 'gd-client-portal'), 'subtitle' => __('Progress across delivery stages', 'gd-client-portal'), 'status' => __('2 tasks due soon', 'gd-client-portal')),
            'files' => array('title' => __('Files & assets', 'gd-client-portal'), 'subtitle' => __('Shared documents and final package files', 'gd-client-portal'), 'status' => __('14 files uploaded', 'gd-client-portal')),
            'deliverables' => array('title' => __('Deliverables', 'gd-client-portal'), 'subtitle' => __('Milestone outputs and package releases', 'gd-client-portal'), 'status' => __('5 deliverables ready', 'gd-client-portal')),
            'messages' => array('title' => __('Inbox', 'gd-client-portal'), 'subtitle' => __('Recent team messages and updates', 'gd-client-portal'), 'status' => __('3 unread messages', 'gd-client-portal')),
            'meetings' => array('title' => __('Meetings', 'gd-client-portal'), 'subtitle' => __('Upcoming reviews and check-ins', 'gd-client-portal'), 'status' => __('2 meetings this week', 'gd-client-portal')),
            'support' => array('title' => __('Support centre', 'gd-client-portal'), 'subtitle' => __('Tickets, help requests, and resolutions', 'gd-client-portal'), 'status' => __('1 open ticket', 'gd-client-portal')),
            'marketplace' => array('title' => __('Marketplace', 'gd-client-portal'), 'subtitle' => __('Add-ons, services, and available offers', 'gd-client-portal'), 'status' => __('4 items available', 'gd-client-portal')),
            'downloads' => array('title' => __('Downloads', 'gd-client-portal'), 'subtitle' => __('Files, assets, and client resources', 'gd-client-portal'), 'status' => __('3 files ready', 'gd-client-portal')),
            'invoices' => array('title' => __('Billing overview', 'gd-client-portal'), 'subtitle' => __('Invoices, payments, and balances', 'gd-client-portal'), 'status' => __('1 payment due', 'gd-client-portal')),
            'account' => array('title' => __('Account details', 'gd-client-portal'), 'subtitle' => __('Profile, business details, and preferences', 'gd-client-portal'), 'status' => __('Profile is current', 'gd-client-portal')),
        );

        $module_data = isset($module_labels[$module]) ? $module_labels[$module] : array(
            'title' => __('Module overview', 'gd-client-portal'),
            'subtitle' => __('This portal module is ready for your custom implementation.', 'gd-client-portal'),
            'status' => __('Ready for updates', 'gd-client-portal'),
        );

        $rows = array(
            array('label' => __('Latest update', 'gd-client-portal'), 'value' => __('Shared by the service team', 'gd-client-portal')),
            array('label' => __('Next action', 'gd-client-portal'), 'value' => __('Review the latest item and confirm completion', 'gd-client-portal')),
            array('label' => __('Owner', 'gd-client-portal'), 'value' => __('Client portal team', 'gd-client-portal')),
        );

        $stat_cards = array(
            array('label' => __('Active', 'gd-client-portal'), 'value' => '4'),
            array('label' => __('Pending', 'gd-client-portal'), 'value' => '2'),
            array('label' => __('Updated', 'gd-client-portal'), 'value' => __('Today', 'gd-client-portal')),
        );

        $html = '<div class="gd-module-detail">';
        $html .= '<header class="gd-module-detail-header">';
        $html .= '<div><span class="gd-module-kicker">' . esc_html($module_data['subtitle']) . '</span><h2>' . esc_html($module_data['title']) . '</h2></div>';
        $html .= '<span class="gd-module-status">' . esc_html($module_data['status']) . '</span>';
        $html .= '</header>';
        $html .= '<div class="gd-module-stats">';
        foreach ($stat_cards as $stat) {
            $html .= '<div class="gd-module-stat"><strong>' . esc_html($stat['value']) . '</strong><span>' . esc_html($stat['label']) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<div class="gd-module-table-wrap"><table class="gd-module-table"><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><th>' . esc_html($row['label']) . '</th><td>' . esc_html($row['value']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
        $html .= '</div>';

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
                        'id' => 'projects',
                        'label' => __('Projects', 'gd-client-portal'),
                        'shortcode' => '[gd_service_projects]',
                        'icon' => '📁',
                        'description' => __('View your active service projects', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'workflow',
                        'label' => __('Workflow', 'gd-client-portal'),
                        'shortcode' => '[gd_workflow_ui]',
                        'icon' => '⚙️',
                        'description' => __('Track project progress through stages', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'files',
                        'label' => __('Files', 'gd-client-portal'),
                        'shortcode' => '[gd_customer_files]',
                        'icon' => '📄',
                        'description' => __('Access your project files securely', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'deliverables',
                        'label' => __('Deliverables', 'gd-client-portal'),
                        'shortcode' => '[gd_project_deliverables]',
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
                        'icon' => '✉️',
                        'description' => __('Send and receive messages', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'meetings',
                        'label' => __('Meetings', 'gd-client-portal'),
                        'shortcode' => '[gd_booking_calendar]',
                        'icon' => '📅',
                        'description' => __('Schedule and manage meetings', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'support',
                        'label' => __('Support', 'gd-client-portal'),
                        'shortcode' => '[gd_support_tickets]',
                        'icon' => '🎟️',
                        'description' => __('Submit support tickets', 'gd-client-portal'),
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
                        'url' => 'https://godemarsempire.com/marketplace/',
                        'icon' => '🏪',
                        'description' => __('Browse and purchase services', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'downloads',
                        'label' => __('Downloads', 'gd-client-portal'),
                        'shortcode' => '[gd_woo_downloads]',
                        'icon' => '⬇️',
                        'description' => __('Access your digital downloads', 'gd-client-portal'),
                    ),
                    array(
                        'id' => 'invoices',
                        'label' => __('Invoices', 'gd-client-portal'),
                        'shortcode' => '[gd_woo_invoices]',
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
                        'url' => 'https://godemarsempire.com/profile/',
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
add_shortcode('gd_woo_invoices', 'gd_client_portal_render_placeholder_shortcode');
add_shortcode('gd_account_settings', 'gd_client_portal_render_placeholder_shortcode');
