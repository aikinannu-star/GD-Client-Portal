<?php
/**
 * Installation and activation hooks for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_activate')) {
    function gd_client_portal_activate()
    {
        global $wpdb;

        add_option('gd_client_portal_installed', time());

        $table_name = $wpdb->prefix . 'gd_projects';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL DEFAULT 0,
            order_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            service_type varchar(100) NOT NULL,
            title varchar(255) NOT NULL,
            description longtext NULL,
            status varchar(100) NOT NULL,
            current_stage varchar(100) NOT NULL,
            progress int(11) NOT NULL DEFAULT 0,
            file_url varchar(255) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tenant_id (tenant_id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Messages table
        $messages_table = $wpdb->prefix . 'gd_project_messages';
        $sql2 = "CREATE TABLE IF NOT EXISTS $messages_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            message longtext NOT NULL,
            file_url varchar(255) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql2);

        // Create a Tenant Admin role with a dedicated capability for tenant-scoped management.
        if (!get_role('gd_client_portal_tenant_admin')) {
            add_role('gd_client_portal_tenant_admin', __('Tenant Admin', 'gd-client-portal'), array(
                'read' => true,
                'gd_client_portal_tenant_admin' => true,
            ));
        }

        // Ensure platform administrators can also access the tenant-scoped admin screens.
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('gd_client_portal_tenant_admin')) {
            $admin_role->add_cap('gd_client_portal_tenant_admin');
        }
    }
}

if (!function_exists('gd_client_portal_ensure_admin_access_caps')) {
    function gd_client_portal_ensure_admin_access_caps()
    {
        $tenant_admin_role = get_role('gd_client_portal_tenant_admin');
        if ($tenant_admin_role && !$tenant_admin_role->has_cap('read')) {
            $tenant_admin_role->add_cap('read');
        }

        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('gd_client_portal_tenant_admin')) {
            $admin_role->add_cap('gd_client_portal_tenant_admin');
        }
    }
    add_action('admin_init', 'gd_client_portal_ensure_admin_access_caps');
}

if (!function_exists('gd_client_portal_deactivate')) {
    function gd_client_portal_deactivate()
    {
        delete_option('gd_client_portal_installed');
    }
}
