<?php
/**
 * Dashboard-related functionality for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Simple in-memory registry for module dashboard views. Modules can register
// a callable that returns HTML for the dashboard detail view.
global $gd_client_portal_dashboard_views;
$gd_client_portal_dashboard_views = isset($gd_client_portal_dashboard_views) && is_array($gd_client_portal_dashboard_views) ? $gd_client_portal_dashboard_views : array();

if (!function_exists('gd_client_portal_register_dashboard_view')) {
    function gd_client_portal_register_dashboard_view($module_key, $callable)
    {
        global $gd_client_portal_dashboard_views;
        if (empty($module_key) || empty($callable)) {
            return false;
        }
        if (!is_callable($callable) && !function_exists($callable)) {
            return false;
        }
        $gd_client_portal_dashboard_views[sanitize_key($module_key)] = $callable;
        return true;
    }
}

if (!function_exists('gd_client_portal_get_registered_dashboard_view')) {
    function gd_client_portal_get_registered_dashboard_view($module_key)
    {
        global $gd_client_portal_dashboard_views;
        $k = sanitize_key($module_key);
        return isset($gd_client_portal_dashboard_views[$k]) ? $gd_client_portal_dashboard_views[$k] : null;
    }
}

if (!function_exists('gd_client_portal_get_all_registered_dashboard_views')) {
    function gd_client_portal_get_all_registered_dashboard_views()
    {
        global $gd_client_portal_dashboard_views;
        return is_array($gd_client_portal_dashboard_views) ? $gd_client_portal_dashboard_views : array();
    }
}

if (!function_exists('gd_client_portal_autoregister_dashboard_views')) {
    function gd_client_portal_autoregister_dashboard_views()
    {
        if (!function_exists('gd_client_portal_register_dashboard_view')) {
            return;
        }

        // Look at configured dashboard sections and register conventional module views
        if (!function_exists('gd_client_portal_get_dashboard_sections')) {
            return;
        }

        $sections = gd_client_portal_get_dashboard_sections();
        foreach ($sections as $section) {
            if (empty($section['cards'])) {
                continue;
            }
            foreach ($section['cards'] as $card) {
                $id = isset($card['id']) ? sanitize_key($card['id']) : '';
                if (empty($id)) {
                    continue;
                }

                $func = 'gd_client_portal_render_' . $id;
                if (function_exists($func)) {
                    gd_client_portal_register_dashboard_view($id, $func);
                    continue;
                }

                // If no render function exists, but a detail template exists, register that template as a callable
                $tpl = GD_CLIENT_PORTAL_PATH . 'modules/' . $id . '/templates/detail.php';
                if (file_exists($tpl)) {
                    $callable = function ($args = array()) use ($tpl) {
                        ob_start();
                        include $tpl;
                        return ob_get_clean();
                    };
                    gd_client_portal_register_dashboard_view($id, $callable);
                }
            }
        }
    }
    add_action('init', 'gd_client_portal_autoregister_dashboard_views', 20);
}

if (!function_exists('gd_client_portal_render_access_gate')) {
    function gd_client_portal_render_access_gate($title = '', $message = '')
    {
        $title = $title ?: __('Client Portal Access', 'gd-client-portal');
        $message = $message ?: __('Please log in or create an account to access your client dashboard.', 'gd-client-portal');

        // Access gate no longer renders inline auth forms; reserved for feature gating.
        return gd_client_portal_load_view('access-gate', array(
            'title' => $title,
            'message' => $message,
        ));
    }
}

if (!function_exists('gd_client_portal_render_dashboard')) {
    function gd_client_portal_render_dashboard()
    {
        if (!gd_client_portal_verify_request()) {
            // For guests, show a simple link to the standalone auth UI (if configured).
            $auth_url = gd_client_portal_get_redirect_page_url('gd_client_portal_auth_page_id', 'gd_client_portal_auth_page_url');
            if (!empty($auth_url)) {
                $message = sprintf(__('Please <a href="%s">log in or create an account</a> to access your dashboard.', 'gd-client-portal'), esc_url($auth_url));
                return gd_client_portal_render_access_gate(__('Client Portal Access', 'gd-client-portal'), $message);
            }

            return gd_client_portal_render_access_gate();
        }

        $branding = function_exists('gd_client_portal_get_current_tenant_branding') ? gd_client_portal_get_current_tenant_branding() : array();
        $dashboard_title = !empty($branding['portal_title']) ? $branding['portal_title'] : get_option('gd_client_portal_dashboard_title', __('Client Dashboard', 'gd-client-portal'));
        $welcome_message = !empty($branding['portal_welcome']) ? $branding['portal_welcome'] : get_option('gd_client_portal_welcome_message', __('Welcome to your client portal.', 'gd-client-portal'));

        // If a module query is present, render the module view on the dashboard
        $module_key = isset($_GET['module']) ? sanitize_text_field(wp_unslash($_GET['module'])) : '';
        $content = gd_client_portal_render_dashboard_sections();
        $module_title = '';
        if ($module_key) {
            $sections = gd_client_portal_get_dashboard_sections();
            foreach ($sections as $section) {
                if (empty($section['cards'])) continue;
                foreach ($section['cards'] as $card) {
                    if (isset($card['id']) && $card['id'] === $module_key) {
                        $module_title = !empty($card['label']) ? $card['label'] : ucfirst($module_key);
                        $module_description = !empty($card['description']) ? $card['description'] : '';

                        // Primary: try the card shortcode (many modules expose a shortcode)
                        $content = '';
                        if (!empty($card['shortcode'])) {
                            $content = do_shortcode($card['shortcode']);
                        }

                        // If shortcode returned nothing useful, try a registered dashboard view,
                        // then a module-specific render function, then module template.
                        if (empty(trim(strip_tags((string) $content)))) {
                            // Registered view (via gd_client_portal_register_dashboard_view)
                            if (function_exists('gd_client_portal_get_registered_dashboard_view')) {
                                $reg = gd_client_portal_get_registered_dashboard_view($module_key);
                                if (!empty($reg)) {
                                    try {
                                        if (is_callable($reg)) {
                                            $content = call_user_func($reg, array());
                                        } elseif (function_exists($reg)) {
                                            $content = call_user_func($reg, array());
                                        }
                                    } catch (Exception $e) {
                                        $content = '<p>' . esc_html__('Unable to load module content.', 'gd-client-portal') . '</p>';
                                    }
                                }
                            }

                            // If still empty, try the conventional render function
                            if (empty(trim((string) $content))) {
                                $module_render_func = 'gd_client_portal_render_' . $module_key;
                                if (function_exists($module_render_func)) {
                                    try {
                                        $content = call_user_func($module_render_func, array());
                                    } catch (Exception $e) {
                                        $content = '<p>' . esc_html__('Unable to load module content.', 'gd-client-portal') . '</p>';
                                    }
                                } else {
                                    // Fallback: include a module detail template if present
                                    $tpl = GD_CLIENT_PORTAL_PATH . 'modules/' . $module_key . '/templates/detail.php';
                                    if (file_exists($tpl)) {
                                        ob_start();
                                        include $tpl;
                                        $content = ob_get_clean();
                                    }
                                }
                            }
                        }

                        // Final fallback: placeholder message
                        if (empty(trim((string) $content))) {
                            $content = '<p>' . esc_html__('This module has no detailed view yet.', 'gd-client-portal') . '</p>' . gd_client_portal_render_dashboard_sections();
                        }

                        break 2;
                    }
                }
            }
        }

        return gd_client_portal_load_view('dashboard', array(
            'title' => $dashboard_title,
            'welcome_message' => $welcome_message,
            'content' => $content,
            'module_title' => $module_title,
            'module_description' => isset($module_description) ? $module_description : '',
            'back_url' => gd_client_portal_get_dashboard_url(),
            'branding' => $branding,
        ));
    }
}

add_shortcode('gd_client_portal_dashboard', 'gd_client_portal_render_dashboard');
add_shortcode('gd_client_portal_access_gate', 'gd_client_portal_render_access_gate');
