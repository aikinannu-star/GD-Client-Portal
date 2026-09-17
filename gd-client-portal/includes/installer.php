<?php
/**
 * Installation and activation hooks for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_activation_step')) {
    /**
     * Run an activation migration without allowing a single optional module
     * failure to abort the entire plugin activation request.
     */
    function gd_client_portal_activation_step($label, $callback)
    {
        try {
            if (is_callable($callback)) {
                call_user_func($callback);
            }
            return true;
        } catch (Throwable $e) {
            $failures = get_option('gd_client_portal_activation_failures', array());
            $failures = is_array($failures) ? $failures : array();
            $failures[] = array(
                'step'    => sanitize_key($label),
                'message' => sanitize_text_field($e->getMessage()),
                'file'    => sanitize_text_field($e->getFile()),
                'line'    => absint($e->getLine()),
                'time'    => current_time('mysql'),
            );
            update_option('gd_client_portal_activation_failures', array_slice($failures, -10), false);
            error_log('[GD Client Portal] Activation step failed: ' . $label . ' — ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('gd_client_portal_activate')) {
    function gd_client_portal_activate()
    {
        global $wpdb;

        // ACTIVATION MUST REMAIN MINIMAL. WordPress executes this callback in
        // the activation request; optional module installers are deferred to
        // normal runtime so one module/database problem can never prevent the
        // plugin itself from activating.
        delete_option('gd_client_portal_activation_failures');
        delete_option('gd_client_portal_bootstrap_failures');

        gd_client_portal_activation_step('core_tables', function () use ($wpdb) {
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
                event_key varchar(64) NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY tenant_id (tenant_id),
                KEY order_id (order_id),
                KEY user_id (user_id),
                KEY product_id (product_id)
            ) $charset_collate;";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            $messages_table = $wpdb->prefix . 'gd_project_messages';
            $sql2 = "CREATE TABLE IF NOT EXISTS $messages_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                project_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                message longtext NOT NULL,
                file_url varchar(255) NULL,
                event_key varchar(64) NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY project_id (project_id),
                KEY user_id (user_id),
                UNIQUE KEY project_event_key (project_id,event_key)
            ) $charset_collate;";
            dbDelta($sql2);
        });

        gd_client_portal_activation_step('roles_and_permissions', function () {
            if (!get_role('gd_client_portal_tenant_admin')) {
                add_role('gd_client_portal_tenant_admin', __('Tenant Admin', 'gd-client-portal'), array(
                    'read' => true,
                    'gd_client_portal_tenant_admin' => true,
                ));
            }
            $admin_role = get_role('administrator');
            if ($admin_role && !$admin_role->has_cap('gd_client_portal_tenant_admin')) {
                $admin_role->add_cap('gd_client_portal_tenant_admin');
            }
            update_option('gd_client_portal_permissions_version', 2);
        });

        // Mark installation and let the normal bootstrap/migration runner
        // create optional tables safely after activation.
        update_option('gd_client_portal_deferred_install_version', '6.4.2', false);
        update_option('gd_client_portal_woo_automation_enabled', '1');
        update_option('gd_client_portal_installed', time());

        if (!wp_next_scheduled('gd_client_portal_automation_daily')) {
            wp_schedule_event(time() + 600, 'daily', 'gd_client_portal_automation_daily');
        }
    }
}

if (!function_exists('gd_client_portal_migrate_tenant_assignments')) {
    /**
     * Backfill tenant IDs for legacy project/order records from the owning
     * WordPress user's explicit tenant assignment. This is intentionally
     * conservative: rows that cannot be mapped remain unassigned (0) and
     * therefore cannot be exposed to a tenant user.
     */
    function gd_client_portal_migrate_tenant_assignments()
    {
        global $wpdb;

        $migration_version = absint(get_option('gd_client_portal_tenant_data_version', 0));
        if ($migration_version >= 1) {
            return;
        }

        $projects = $wpdb->prefix . 'gd_projects';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $projects)) === $projects) {
            $wpdb->query("UPDATE {$projects} p INNER JOIN {$wpdb->usermeta} um ON um.user_id = p.user_id AND um.meta_key = 'gd_client_portal_tenant_id' SET p.tenant_id = CAST(um.meta_value AS UNSIGNED) WHERE (p.tenant_id IS NULL OR p.tenant_id = 0) AND um.meta_value REGEXP '^[0-9]+$' AND CAST(um.meta_value AS UNSIGNED) > 0");
        }

        // Marketplace order posts store tenant scope in post meta.
        $orders = $wpdb->posts;
        $wpdb->query("UPDATE {$wpdb->postmeta} pm_tenant INNER JOIN {$orders} p ON p.ID = pm_tenant.post_id INNER JOIN {$wpdb->usermeta} um ON um.user_id = p.post_author AND um.meta_key = 'gd_client_portal_tenant_id' LEFT JOIN {$wpdb->postmeta} existing ON existing.post_id = p.ID AND existing.meta_key = '_gd_mp_order_tenant_id' SET pm_tenant.meta_value = CAST(um.meta_value AS UNSIGNED) WHERE pm_tenant.meta_key = '_gd_mp_order_tenant_id' AND (pm_tenant.meta_value IS NULL OR pm_tenant.meta_value = '' OR pm_tenant.meta_value = '0') AND existing.meta_id = pm_tenant.meta_id AND um.meta_value REGEXP '^[0-9]+$' AND CAST(um.meta_value AS UNSIGNED) > 0 AND p.post_type = 'gd_client_portal_marketplace_order'");

        update_option('gd_client_portal_tenant_data_version', 1);
    }
}

if (!function_exists('gd_client_portal_ensure_admin_access_caps')) {
    function gd_client_portal_ensure_admin_access_caps()
    {

        $tenant_admin_role = get_role('gd_client_portal_tenant_admin');
        if (!$tenant_admin_role) {
            $tenant_admin_role = add_role(
                'gd_client_portal_tenant_admin',
                __('Tenant Admin', 'gd-client-portal'),
                array(
                    'read' => true,
                    'gd_client_portal_tenant_admin' => true,
                )
            );
        }

        if ($tenant_admin_role && !$tenant_admin_role->has_cap('read')) {
            $tenant_admin_role->add_cap('read');
        }
        if ($tenant_admin_role && !$tenant_admin_role->has_cap('gd_client_portal_tenant_admin')) {
            $tenant_admin_role->add_cap('gd_client_portal_tenant_admin');
        }

        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('gd_client_portal_tenant_admin')) {
            $admin_role->add_cap('gd_client_portal_tenant_admin');
        }

        if (absint(get_option('gd_client_portal_permissions_version', 0)) !== 2) {
            update_option('gd_client_portal_permissions_version', 2);
        }
    }

    // IMPORTANT: admin_menu runs before admin_init. Provision capabilities
    // before WordPress evaluates add_menu_page()/add_submenu_page().
    add_action('plugins_loaded', 'gd_client_portal_ensure_admin_access_caps', 20);
    add_action('admin_init', 'gd_client_portal_ensure_admin_access_caps', 1);
}

if (!function_exists('gd_client_portal_deactivate')) {
    function gd_client_portal_deactivate()
    {
        delete_option('gd_client_portal_installed');
        // Clear every plugin-owned recurring and single-event hook so a
        // deactivated site cannot retain background work from this plugin.
        $hooks = array(
            'gd_client_portal_payment_daily',
            'gd_client_portal_automation_daily',
            'gd_client_portal_automation_reliability_tick',
            'gd_client_portal_sla_daily',
            'gd_client_portal_automation_queue_runner',
        );
        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}

// v4.0 automation schedule. Scheduled during activation and cleared on deactivation.
add_action('gd_client_portal_automation_daily',function(){do_action('gd_client_portal_sla_daily');},1);

// Deferred optional schema setup. This runs after the plugin is fully loaded,
// never inside WordPress's activation callback. Every step is isolated.
if (!function_exists('gd_client_portal_deferred_install')) {
    function gd_client_portal_deferred_install() {
        if (get_option('gd_client_portal_deferred_install_version', '') !== '6.4.2') return;
        $steps = array(
            'tenant_migration' => 'gd_client_portal_migrate_tenant_assignments',
            'audit' => 'gd_client_portal_activate_audit_table',
            'approvals' => 'gd_client_portal_activate_approval_table',
            'sla' => 'gd_client_portal_activate_sla_table',
            'contracts' => 'gd_client_portal_contracts_activate_table',
            'contract_signatures' => 'gd_client_portal_contracts_activate_signature_table',
            'notifications' => 'gd_client_portal_notifications_activate',
            'delivery' => 'gd_client_portal_activate_delivery_table',
            'assignments' => 'gd_client_portal_activate_assignments_table',
            'calendar' => 'gd_client_portal_activate_calendar_table',
            'onboarding' => 'gd_client_portal_activate_onboarding_tables',
            'intake' => 'gd_client_portal_activate_intake_tables',
            'requests' => 'gd_client_portal_activate_requests_table',
            'billing' => 'gd_client_portal_billing_activate_tables',
            'payments' => 'gd_client_portal_payment_activate_table',
            'documents' => 'gd_client_portal_documents_activate_tables',
            'collaboration' => 'gd_client_portal_activate_collaboration_tables',
            'support' => 'gd_client_portal_support_install',
            'feedback' => 'gd_client_portal_feedback_install',
            'automation' => 'gd_client_portal_automation_activate_tables',
        );
        foreach ($steps as $label => $callback) {
            if (function_exists($callback)) gd_client_portal_activation_step('deferred_'.$label, $callback);
        }
        if (function_exists('gd_client_portal_platform_activation')) gd_client_portal_activation_step('deferred_platform', 'gd_client_portal_platform_activation');
        if (function_exists('gd_client_portal_automation_seed_defaults')) gd_client_portal_activation_step('deferred_automation_defaults', 'gd_client_portal_automation_seed_defaults');
        update_option('gd_client_portal_deferred_install_version', '6.4.2-complete', false);
    }
}
// Schema setup is centrally managed by GDCP_Migration_Manager from v6.8+.

