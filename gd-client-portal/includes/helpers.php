<?php
/**
 * Shared helper utilities for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_cached_user')) {
    /**
     * Request-local memoization for repeated user lookups in portal renderers.
     * This avoids duplicate WP_User queries without introducing stale cross-request cache data.
     */
    function gd_client_portal_cached_user($user_id)
    {
        static $cache = array();
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }
        if (array_key_exists($user_id, $cache)) {
            return $cache[$user_id];
        }
        $cache[$user_id] = get_userdata($user_id);
        return $cache[$user_id];
    }
}

if (!function_exists('gd_client_portal_cached_project')) {
    /** Request-local memoization for repeated project row lookups. */
    function gd_client_portal_cached_project($project_id)
    {
        static $cache = array();
        $project_id = absint($project_id);
        if ($project_id <= 0) {
            return null;
        }
        if (array_key_exists($project_id, $cache)) {
            return $cache[$project_id];
        }
        global $wpdb;
        $table = gd_client_portal_get_project_table_name();
        $cache[$project_id] = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $project_id));
        return $cache[$project_id];
    }
}

if (!function_exists('gd_client_portal_cached_projects')) {
    /**
     * Request-local bulk project lookup. Useful for timeline/report views that
     * already know a bounded set of project IDs; reduces N+1 row queries to one.
     */
    function gd_client_portal_cached_projects($project_ids)
    {
        global $wpdb;
        static $cache = array();
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $project_ids))));
        if (!$ids) return array();
        $missing = array();
        foreach ($ids as $id) {
            if (array_key_exists($id, $cache)) continue;
            $missing[] = $id;
        }
        if ($missing) {
            $placeholders = implode(',', array_fill(0, count($missing), '%d'));
            $table = gd_client_portal_get_project_table_name();
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders})", $missing));
            foreach ((array) $rows as $row) $cache[absint($row->id)] = $row;
            foreach ($missing as $id) if (!array_key_exists($id, $cache)) $cache[$id] = null;
        }
        $out = array();
        foreach ($ids as $id) $out[$id] = $cache[$id];
        return $out;
    }
}

if (!function_exists('gd_client_portal_cached_current_user_id')) {
    /** Resolve the current user ID once per request. */
    function gd_client_portal_cached_current_user_id()
    {
        static $user_id = null;
        if ($user_id === null) {
            $user_id = absint(get_current_user_id());
        }
        return $user_id;
    }
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

if (!function_exists('gd_client_portal_is_platform_admin')) {
    function gd_client_portal_is_platform_admin($user_id = 0)
    {
        $user_id = absint($user_id ?: get_current_user_id());
        return $user_id > 0 && user_can($user_id, 'manage_options');
    }
}

if (!function_exists('gd_client_portal_get_current_tenant_id')) {
    function gd_client_portal_get_current_tenant_id($user_id = 0)
    {
        $user_id = absint($user_id ?: get_current_user_id());
        if ($user_id <= 0) return 0;
        if (class_exists('GDCP_Tenant_Service')) return absint((gdcp_service('tenant')->current_id($user_id)));
        if (gd_client_portal_is_platform_admin($user_id)) return 0;
        return absint(get_user_meta($user_id, 'gd_client_portal_tenant_id', true));
    }
}

if (!function_exists('gd_client_portal_get_user_tenant_id')) {
    function gd_client_portal_get_user_tenant_id($user_id = 0) { return gd_client_portal_get_current_tenant_id($user_id); }
}

if (!function_exists('gd_client_portal_get_default_tenant_id')) {
    /**
     * Return the configured default tenant only when it still exists.
     * A default tenant is used for newly registered non-platform users.
     */
    function gd_client_portal_get_default_tenant_id()
    {
        $default_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
        if ($default_id <= 0) {
            return 0;
        }

        $tenants = gd_client_portal_get_all_tenants();
        return isset($tenants[$default_id]) ? $default_id : 0;
    }
}

if (!function_exists('gd_client_portal_assign_default_tenant')) {
    /**
     * Assign a newly created non-platform user to the configured default tenant.
     * Existing explicit assignments are never overwritten.
     */
    function gd_client_portal_assign_default_tenant($user_id = 0)
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return 0;
        }

        // Platform administrators are global and must not be tenant-scoped.
        if (gd_client_portal_is_platform_admin($user_id)) {
            return 0;
        }

        // Preserve a valid explicit assignment; stale assignments are treated as unassigned.
        $existing = absint(get_user_meta($user_id, 'gd_client_portal_tenant_id', true));
        if ($existing > 0) {
            $tenant_service = class_exists('GDCP_Tenant_Service') ? gdcp_service('tenant') : null;
            $existing_tenant = $tenant_service ? $tenant_service->get($existing) : null;
            if ($existing_tenant && (($existing_tenant['status'] ?? 'active') === 'active')) {
                return $existing;
            }
            if ($tenant_service) {
                $tenant_service->set_user_tenant($user_id, 0);
            }
        }

        $default_id = gd_client_portal_get_default_tenant_id();
        if ($default_id <= 0) {
            return 0;
        }

        if (class_exists('GDCP_Tenant_Service')) {
            return gdcp_service('tenant')->set_user_tenant($user_id, $default_id) ? $default_id : 0;
        }
        update_user_meta($user_id, 'gd_client_portal_tenant_id', $default_id);
        return $default_id;
    }
}

if (!function_exists('gd_client_portal_assign_default_tenant_on_registration')) {
    /**
     * Safety net for every WordPress user-registration path, including the
     * portal auth UI, WooCommerce/customer registration, and native WP signup.
     */
    function gd_client_portal_assign_default_tenant_on_registration($user_id)
    {
        gd_client_portal_assign_default_tenant($user_id);
    }
    add_action('user_register', 'gd_client_portal_assign_default_tenant_on_registration', 20, 1);
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
        if (current_user_can('manage_options')) {
            return $items;
        }
        if ($tenant_id <= 0) {
            return array();
        }

        $filtered = array();
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_tenant = isset($item['tenant_id']) ? absint($item['tenant_id']) : 0;
            // Unassigned records are platform-only. Tenant users must never
            // inherit visibility of tenant_id=0 content.
            if ($item_tenant > 0 && $item_tenant === $tenant_id) {
                $filtered[$key] = $item;
            }
        }

        return $filtered;
    }
}

if (!function_exists('gd_client_portal_get_all_tenants')) {
    function gd_client_portal_get_all_tenants()
    {
        if (class_exists('GDCP_Tenant_Service')) {
            return gdcp_service('tenant')->all(false);
        }
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
                'status' => in_array(($tenant['status'] ?? 'active'), array('active', 'inactive'), true) ? $tenant['status'] : 'active',
                'logo_url' => esc_url_raw($tenant['logo_url'] ?? ''),
                'primary_contact' => sanitize_text_field($tenant['primary_contact'] ?? ''),
                'email' => sanitize_email($tenant['email'] ?? ''),
                'phone' => sanitize_text_field($tenant['phone'] ?? ''),
                'address' => sanitize_textarea_field($tenant['address'] ?? ''),
                'created_at' => sanitize_text_field($tenant['created_at'] ?? ''),
                'primary_color' => sanitize_hex_color($tenant['primary_color'] ?? '') ?: '#2563eb',
                'accent_color' => sanitize_hex_color($tenant['accent_color'] ?? '') ?: '#0f172a',
                'portal_title' => sanitize_text_field($tenant['portal_title'] ?? ''),
                'portal_welcome' => sanitize_textarea_field($tenant['portal_welcome'] ?? ''),
                'button_label' => sanitize_text_field($tenant['button_label'] ?? ''),
            );
        }

        return $formatted;
    }
}

if (!function_exists('gd_client_portal_get_users_for_tenant')) {
    function gd_client_portal_get_users_for_tenant($tenant_id, $limit = -1)
    {
        $tenant_id = absint($tenant_id);
        if ($tenant_id <= 0) {
            return array();
        }

        static $cache = array();
        $key = $tenant_id . ':' . ($limit > 0 ? absint($limit) : -1);
        if (array_key_exists($key, $cache)) return $cache[$key];
        $cache[$key] = get_users(array(
            'meta_key' => 'gd_client_portal_tenant_id',
            'meta_value' => (string) $tenant_id,
            'number' => $limit > 0 ? absint($limit) : -1,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ));
        return $cache[$key];
    }
}

if (!function_exists('gd_client_portal_set_user_tenant')) {
    function gd_client_portal_set_user_tenant($user_id, $tenant_id)
    {
        if (function_exists('gdcp_service')) { $service = gdcp_service('tenant'); if ($service) return $service->set_user_tenant($user_id, $tenant_id); }
        return false;
    }
}


if (!function_exists('gd_client_portal_get_current_tenant_branding')) {
    /**
     * Resolve the current user's tenant branding. Platform administrators can
     * preview a tenant by passing $tenant_id; otherwise their global portal
     * branding remains available through the platform defaults.
     */
    function gd_client_portal_get_current_tenant_branding($tenant_id = 0)
    {
        $tenant_id = absint($tenant_id ?: gd_client_portal_get_current_tenant_id());
        $defaults = array(
            'name' => __('Client Portal', 'gd-client-portal'),
            'logo_url' => '',
            'primary_color' => '#2563eb',
            'accent_color' => '#0f172a',
            'portal_title' => '',
            'portal_welcome' => '',
            'button_label' => '',
        );

        if ($tenant_id <= 0) {
            $defaults['portal_title'] = get_option('gd_client_portal_dashboard_title', __('Client Dashboard', 'gd-client-portal'));
            $defaults['portal_welcome'] = get_option('gd_client_portal_welcome_message', __('Welcome to your client portal.', 'gd-client-portal'));
            return $defaults;
        }

        $tenants = gd_client_portal_get_all_tenants();
        if (empty($tenants[$tenant_id])) {
            return $defaults;
        }

        $tenant = $tenants[$tenant_id];
        $defaults['name'] = $tenant['name'];
        $defaults['logo_url'] = $tenant['logo_url'];
        $defaults['primary_color'] = $tenant['primary_color'];
        $defaults['accent_color'] = $tenant['accent_color'];
        $defaults['portal_title'] = $tenant['portal_title'] ?: sprintf(__('%s Client Portal', 'gd-client-portal'), $tenant['name']);
        $defaults['portal_welcome'] = $tenant['portal_welcome'] ?: sprintf(__('Welcome to your %s client workspace.', 'gd-client-portal'), $tenant['name']);
        $defaults['button_label'] = $tenant['button_label'] ?: __('Open Workspace', 'gd-client-portal');
        return $defaults;
    }
}

if (!function_exists('gd_client_portal_get_branding_tenant_id')) {
    function gd_client_portal_get_branding_tenant_id()
    {
        if (gd_client_portal_is_platform_admin()) {
            return absint($_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
        }
        return gd_client_portal_get_current_tenant_id();
    }
}
