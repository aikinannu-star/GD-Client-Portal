<?php
/**
 * Shared helper utilities for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_load_view')) {
    function gd_client_portal_load_view($template, $vars = array())
    {
        $template_path = GD_CLIENT_PORTAL_PATH . 'templates/' . $template . '.php';

        if (!file_exists($template_path)) {
            return '';
        }

        extract($vars);
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}

if (!function_exists('gd_client_portal_is_woocommerce_active')) {
    function gd_client_portal_is_woocommerce_active()
    {
        return class_exists('WooCommerce') || function_exists('wc_get_orders');
    }
}

if (!function_exists('gd_client_portal_get_plugin_path')) {
    function gd_client_portal_get_plugin_path($relative_path = '')
    {
        return GD_CLIENT_PORTAL_PATH . ltrim($relative_path, '/');
    }
}

if (!function_exists('gd_client_portal_get_plugin_url')) {
    function gd_client_portal_get_plugin_url($relative_path = '')
    {
        return GD_CLIENT_PORTAL_URL . ltrim($relative_path, '/');
    }
}

if (!function_exists('gd_client_portal_get_redirect_page_url')) {
    function gd_client_portal_get_redirect_page_url($page_id_option_name, $legacy_url_option_name = '')
    {
        $page_id = absint(get_option($page_id_option_name, 0));
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            return $permalink ? $permalink : '';
        }

        $legacy_url = get_option($legacy_url_option_name, '');
        return is_string($legacy_url) && $legacy_url !== '' ? $legacy_url : '';
    }
}

if (!function_exists('gd_client_portal_get_current_tenant_id')) {
    function gd_client_portal_get_current_tenant_id($user_id = 0)
    {
        $user_id = absint($user_id ?: get_current_user_id());

        if ($user_id > 0) {
            $tenant_id = get_user_meta($user_id, 'gd_client_portal_tenant_id', true);
            if ($tenant_id !== '' && $tenant_id !== null) {
                return absint($tenant_id);
            }
        }

        return absint(get_option('gd_client_portal_default_tenant_id', 0));
    }
}

if (!function_exists('gd_client_portal_get_tenant_scope')) {
    function gd_client_portal_get_tenant_scope($tenant_id = null)
    {
        if ($tenant_id !== null) {
            return absint($tenant_id);
        }

        if (current_user_can('manage_options')) {
            return 0;
        }

        return gd_client_portal_get_current_tenant_id();
    }
}

if (!function_exists('gd_client_portal_filter_items_by_tenant')) {
    function gd_client_portal_filter_items_by_tenant($items = array(), $tenant_id = null)
    {
        if (!is_array($items)) {
            return array();
        }

        $tenant_id = absint($tenant_id !== null ? $tenant_id : gd_client_portal_get_current_tenant_id());
        if ($tenant_id <= 0 || current_user_can('manage_options')) {
            return $items;
        }

        $filtered = array();
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_tenant = isset($item['tenant_id']) ? absint($item['tenant_id']) : 0;
            if ($item_tenant === 0 || $item_tenant === $tenant_id) {
                $filtered[$key] = $item;
            }
        }

        return $filtered;
    }
}

if (!function_exists('gd_client_portal_get_all_tenants')) {
    function gd_client_portal_get_all_tenants()
    {
        $tenants = get_option('gd_client_portal_tenants', array());
        if (!is_array($tenants)) {
            $tenants = array();
        }

        if (empty($tenants)) {
            $default_tenant_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
            if ($default_tenant_id > 0) {
                return array(
                    $default_tenant_id => array(
                        'id' => $default_tenant_id,
                        'name' => __('Default tenant', 'gd-client-portal'),
                        'slug' => 'default-tenant',
                    ),
                );
            }
            return array();
        }

        $formatted = array();
        foreach ($tenants as $tenant) {
            if (!is_array($tenant)) {
                continue;
            }

            $id = absint($tenant['id'] ?? 0);
            $name = isset($tenant['name']) ? sanitize_text_field($tenant['name']) : '';
            if ($id <= 0 || $name === '') {
                continue;
            }

            $formatted[$id] = array(
                'id' => $id,
                'name' => $name,
                'slug' => sanitize_title($tenant['slug'] ?? $name),
            );
        }

        return $formatted;
    }
}

if (!function_exists('gd_client_portal_set_user_tenant')) {
    function gd_client_portal_set_user_tenant($user_id, $tenant_id)
    {
        $user_id = absint($user_id);
        $tenant_id = absint($tenant_id);

        if ($user_id <= 0) {
            return false;
        }

        if ($tenant_id > 0) {
            update_user_meta($user_id, 'gd_client_portal_tenant_id', $tenant_id);
            return true;
        }

        delete_user_meta($user_id, 'gd_client_portal_tenant_id');
        return true;
    }
}
