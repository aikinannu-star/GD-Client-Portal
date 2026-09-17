<?php
/**
 * Admin UI for the GD Client Portal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_add_settings_link')) {
    function gd_client_portal_add_settings_link($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=gd-client-portal-settings')) . '">' . esc_html__('Settings', 'gd-client-portal') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }
}


/**
 * Temporary access diagnostic screen.
 *
 * This page intentionally uses the built-in `read` capability so that we can
 * diagnose GD Client Portal capability/menu registration independently of the
 * plugin's custom admin capability. It exposes only non-sensitive capability
 * and registration state for the currently logged-in user.
 */
if (!function_exists('gd_client_portal_register_diagnostic_menu')) {
    function gd_client_portal_register_diagnostic_menu()
    {
        add_dashboard_page(
            __('GD Client Portal Diagnostics', 'gd-client-portal'),
            __('GD Portal Diagnostics', 'gd-client-portal'),
            'read',
            'gd-client-portal-diagnostics',
            'gd_client_portal_render_diagnostic_page'
        );
    }
    add_action('admin_menu', 'gd_client_portal_register_diagnostic_menu', 1);
}

if (!function_exists('gd_client_portal_render_diagnostic_page')) {
    function gd_client_portal_render_diagnostic_page()
    {
        if (!current_user_can('read')) {
            wp_die(esc_html__('You do not have permission to access this diagnostic page.', 'gd-client-portal'));
        }

        global $submenu, $menu, $_registered_pages, $_wp_submenu_nopriv, $pagenow;

        $user = wp_get_current_user();
        $user_id = gd_client_portal_cached_current_user_id();
        $roles = $user ? (array) $user->roles : array();
        $plugin_version = defined('GD_CLIENT_PORTAL_VERSION') ? GD_CLIENT_PORTAL_VERSION : 'unknown';
        $plugin_file = defined('GD_CLIENT_PORTAL_PATH') ? GD_CLIENT_PORTAL_PATH . 'gd-client-portal.php' : 'unknown';
        $tenant_role = get_role('gd_client_portal_tenant_admin');
        $admin_role = get_role('administrator');
        $tenant_id = function_exists('gd_client_portal_get_current_tenant_id') ? absint(gd_client_portal_get_current_tenant_id()) : 0;
        $tenant_count = function_exists('gd_client_portal_get_all_tenants') ? count((array) gd_client_portal_get_all_tenants()) : 0;

        $access_cap = 'gd_client_portal_access_admin';
        $tenant_cap = 'gd_client_portal_tenant_admin';
        $expected_tenants_hook = function_exists('get_plugin_page_hookname')
            ? get_plugin_page_hookname('gd-client-portal-tenants', 'gd-client-portal')
            : '';
        $tenants_page_registered = $expected_tenants_hook !== '' && isset($_registered_pages[$expected_tenants_hook]);
        $tenant_registered_hooks = array();
        foreach (array_keys((array) $_registered_pages) as $registered_hook) {
            if (strpos($registered_hook, 'gd-client-portal-tenants') !== false) {
                $tenant_registered_hooks[] = $registered_hook;
            }
        }
        $tenants_blocked_for_nonpriv = isset($_wp_submenu_nopriv['gd-client-portal']['gd-client-portal-tenants']);
        $portal_submenus = isset($submenu['gd-client-portal']) && is_array($submenu['gd-client-portal'])
            ? $submenu['gd-client-portal']
            : array();

        $tenant_menu_entries = array();
        foreach ($portal_submenus as $entry) {
            if (isset($entry[2]) && $entry[2] === 'gd-client-portal-tenants') {
                $tenant_menu_entries[] = $entry;
            }
        }

        $map_filter_registered = has_filter('map_meta_cap', 'gd_client_portal_map_admin_access_capability') !== false;
        $permission_version = absint(get_option('gd_client_portal_permissions_version', 0));

        $rows = array(
            'WordPress user ID' => $user_id,
            'Roles' => !empty($roles) ? implode(', ', $roles) : '(none)',
            'GD Client Portal version' => $plugin_version,
            'Plugin file' => $plugin_file,
            'Current tenant ID' => $tenant_id > 0 ? $tenant_id : '(none)',
            'Configured tenant count' => $tenant_count,
            'Permissions migration version' => $permission_version,
            'read' => current_user_can('read') ? 'YES' : 'NO',
            'manage_options' => current_user_can('manage_options') ? 'YES' : 'NO',
            $tenant_cap => current_user_can($tenant_cap) ? 'YES' : 'NO',
            $access_cap => current_user_can($access_cap) ? 'YES' : 'NO',
            'map_meta_cap filter registered' => $map_filter_registered ? 'YES' : 'NO',
            'Tenant Admin role exists' => $tenant_role ? 'YES' : 'NO',
            'Tenant Admin role has tenant capability' => $tenant_role && $tenant_role->has_cap($tenant_cap) ? 'YES' : 'NO',
            'Administrator role has tenant capability' => $admin_role && $admin_role->has_cap($tenant_cap) ? 'YES' : 'NO',
            'Tenants submenu registered' => !empty($tenant_menu_entries) ? 'YES' : 'NO',
            'Expected Tenants page hook' => $expected_tenants_hook !== '' ? $expected_tenants_hook : '(unknown)',
            'Tenants page hook registered' => $tenants_page_registered ? 'YES' : 'NO',
            'Registered Tenants hook(s)' => !empty($tenant_registered_hooks) ? implode(', ', $tenant_registered_hooks) : '(none)',
            'Tenants page marked no-privilege' => $tenants_blocked_for_nonpriv ? 'YES' : 'NO',
            'Current admin page' => isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : $pagenow,
        );

        $all_good = current_user_can($access_cap) && $map_filter_registered && !empty($tenant_menu_entries) && $tenants_page_registered;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('GD Client Portal Diagnostics', 'gd-client-portal'); ?></h1>
            <p><?php echo esc_html__('This diagnostic page is intentionally independent of the GD Client Portal admin capability. It helps identify whether WordPress is loading the expected plugin copy and whether the tenant admin capability/menu is being registered correctly.', 'gd-client-portal'); ?></p>

            <div class="notice <?php echo $all_good ? 'notice-success' : 'notice-warning'; ?> inline">
                <p><strong><?php echo $all_good ? esc_html__('Core access checks look healthy.', 'gd-client-portal') : esc_html__('A permission or menu-registration problem was detected.', 'gd-client-portal'); ?></strong></p>
            </div>

            <table class="widefat striped" style="max-width:1100px; margin-top:16px;">
                <thead><tr><th style="width:45%;"><?php echo esc_html__('Check', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Result', 'gd-client-portal'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $label => $value) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td><code><?php echo esc_html((string) $value); ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:28px;"><?php echo esc_html__('Tenants submenu registration', 'gd-client-portal'); ?></h2>
            <?php if (empty($tenant_menu_entries)) : ?>
                <p><strong><?php echo esc_html__('The Tenants submenu was not found in WordPress\'s registered submenu array for this request.', 'gd-client-portal'); ?></strong></p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:1100px;">
                    <thead><tr><th><?php echo esc_html__('Title', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Capability', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Slug', 'gd-client-portal'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($tenant_menu_entries as $entry) : ?>
                        <tr>
                            <td><?php echo esc_html($entry[0] ?? ''); ?></td>
                            <td><code><?php echo esc_html($entry[1] ?? ''); ?></code></td>
                            <td><code><?php echo esc_html($entry[2] ?? ''); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top:28px;"><?php echo esc_html__('What to send back', 'gd-client-portal'); ?></h2>
            <p><?php echo esc_html__('Open this page, then send me a screenshot of the diagnostic table. The important rows are: GD Client Portal version, Roles, manage_options, gd_client_portal_tenant_admin, gd_client_portal_access_admin, map_meta_cap filter registered, Tenants submenu registered, Tenants page hook registered, and Tenants page marked no-privilege.', 'gd-client-portal'); ?></p>
            <p><strong><?php echo esc_html__('Diagnostic URL:', 'gd-client-portal'); ?></strong> <code><?php echo esc_html(admin_url('index.php?page=gd-client-portal-diagnostics')); ?></code></p>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_register_admin_menu')) {
    function gd_client_portal_register_admin_menu()
    {
        add_menu_page(
            __('GD Client Portal', 'gd-client-portal'),
            __('GD Client Portal', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal',
            'gd_client_portal_render_admin_page',
            'dashicons-admin-home',
            56
        );

        add_submenu_page(
            'gd-client-portal',
            __('Dashboard', 'gd-client-portal'),
            __('Dashboard', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal',
            'gd_client_portal_render_admin_page'
        );

        add_submenu_page(
            'gd-client-portal',
            __('Settings', 'gd-client-portal'),
            __('Settings', 'gd-client-portal'),
            'manage_options',
            'gd-client-portal-settings',
            'gd_client_portal_render_settings_page'
        );
    }
}

if (!function_exists('gd_client_portal_redirect_legacy_admin_slug')) {
    function gd_client_portal_redirect_legacy_admin_slug()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri === '') {
            return;
        }

        $path = wp_parse_url($uri, PHP_URL_PATH);
        if ($path && preg_match('#/(?:wp-admin/)?gd-client-portal-tenants/?$#', $path)) {
            wp_safe_redirect(admin_url('admin.php?page=gd-client-portal-tenants'));
            exit;
        }
    }
    add_action('parse_request', 'gd_client_portal_redirect_legacy_admin_slug');
    add_action('admin_init', 'gd_client_portal_redirect_legacy_admin_slug');
}

if (!function_exists('gd_client_portal_register_settings')) {
    function gd_client_portal_register_settings()
    {
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_dashboard_title');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_welcome_message');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_enable_sections');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_woo_redirect_after_service');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_auth_page_id');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_dashboard_page_id');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_auth_page_url');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_dashboard_page_url');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_paystack_enabled');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_paystack_currency');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_paystack_public_key');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_paystack_secret_key');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_paystack_webhook_secret');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_default_tenant_id');
        register_setting('gd_client_portal_settings_group', 'gd_client_portal_enabled_modules');
    }
}

if (!function_exists('gd_client_portal_register_tenant_menu')) {
    function gd_client_portal_register_tenant_menu()
    {
        add_submenu_page(
            'gd-client-portal',
            __('Tenants', 'gd-client-portal'),
            __('Tenants', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-tenants',
            'gd_client_portal_render_tenants_page'
        );
    }
    // Register after the top-level GD Client Portal menu so WordPress has
    // already created its admin_page_hooks entry. If this submenu is added
    // before the parent, WordPress can generate a different hook suffix and
    // direct access to the page may fail with "Sorry, you are not allowed".
    add_action('admin_menu', 'gd_client_portal_register_tenant_menu', 20);
}

if (!function_exists('gd_client_portal_handle_tenant_save')) {
    function gd_client_portal_handle_tenant_save()
    {
        if (empty($_POST['gd_client_portal_tenant_action'])) {
            return;
        }

        // Only platform administrators may create, update, delete, or change
        // the global default tenant. Tenant administrators get a read-only
        // view of their assigned tenant instead.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Only a platform administrator can modify tenants.', 'gd-client-portal'));
        }

        if (empty($_POST['gd_client_portal_tenant_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_tenant_nonce']), 'gd_client_portal_tenant_save')) {
            wp_die(esc_html__('Invalid request.', 'gd-client-portal'));
        }

        $action = sanitize_text_field(wp_unslash($_POST['gd_client_portal_tenant_action']));
        $tenants = gd_client_portal_get_all_tenants();

        if (in_array($action, array('add', 'update'), true)) {
            $tenant_name = isset($_POST['tenant_name']) ? sanitize_text_field(wp_unslash($_POST['tenant_name'])) : '';
            $tenant_slug = isset($_POST['tenant_slug']) ? sanitize_title(wp_unslash($_POST['tenant_slug'])) : '';
            $tenant_status = (isset($_POST['tenant_status']) && wp_unslash($_POST['tenant_status']) === 'inactive') ? 'inactive' : 'active';
            $tenant_logo = isset($_POST['tenant_logo_url']) ? esc_url_raw(wp_unslash($_POST['tenant_logo_url'])) : '';
            $tenant_contact = isset($_POST['tenant_primary_contact']) ? sanitize_text_field(wp_unslash($_POST['tenant_primary_contact'])) : '';
            $tenant_email = isset($_POST['tenant_email']) ? sanitize_email(wp_unslash($_POST['tenant_email'])) : '';
            $tenant_phone = isset($_POST['tenant_phone']) ? sanitize_text_field(wp_unslash($_POST['tenant_phone'])) : '';
            $tenant_address = isset($_POST['tenant_address']) ? sanitize_textarea_field(wp_unslash($_POST['tenant_address'])) : '';
            $tenant_id = isset($_POST['tenant_id']) ? absint(wp_unslash($_POST['tenant_id'])) : 0;

            if ($tenant_name === '') {
                wp_die(esc_html__('Tenant name is required.', 'gd-client-portal'));
            }

            if ($tenant_id > 0) {
                if (isset($tenants[$tenant_id])) {
                    $tenant = $tenants[$tenant_id];
                    $tenant['name'] = $tenant_name;
                    $tenant['slug'] = $tenant_slug !== '' ? $tenant_slug : sanitize_title($tenant_name);
                    $tenant['status'] = $tenant_status;
                    $tenant['logo_url'] = $tenant_logo;
                    $tenant['primary_contact'] = $tenant_contact;
                    $tenant['email'] = $tenant_email;
                    $tenant['phone'] = $tenant_phone;
                    $tenant['address'] = $tenant_address;
                    $tenants[$tenant_id] = $tenant;
                }
            } else {
                $tenant_id = time();
                $tenants[$tenant_id] = array(
                    'id' => $tenant_id,
                    'name' => $tenant_name,
                    'slug' => $tenant_slug !== '' ? $tenant_slug : sanitize_title($tenant_name),
                    'status' => $tenant_status,
                    'logo_url' => $tenant_logo,
                    'primary_contact' => $tenant_contact,
                    'email' => $tenant_email,
                    'phone' => $tenant_phone,
                    'address' => $tenant_address,
                    'created_at' => current_time('mysql'),
                );
            }

            update_option('gd_client_portal_tenants', $tenants);

            if (absint(get_option('gd_client_portal_default_tenant_id', 0)) === 0) {
                update_option('gd_client_portal_default_tenant_id', $tenant_id);
            }

            wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-tenants')));
            exit;
        }

        if ($action === 'delete') {
            $tenant_id = isset($_POST['tenant_id']) ? absint(wp_unslash($_POST['tenant_id'])) : 0;
            if ($tenant_id > 0 && isset($tenants[$tenant_id])) {
                $user_count = count(gd_client_portal_get_users_for_tenant($tenant_id));
                global $wpdb;
                $project_table = gd_client_portal_get_project_table_name();
                $project_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$project_table} WHERE tenant_id = %d",
                    $tenant_id
                ));
                $order_query = new WP_Query(array(
                    'post_type' => 'gd_client_portal_marketplace_order',
                    'post_status' => 'any',
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_query' => array(array('key' => '_gd_mp_order_tenant_id', 'value' => (string) $tenant_id, 'compare' => '=')),
                ));
                $order_count = (int) $order_query->found_posts;
                wp_reset_postdata();

                if ($user_count > 0 || $project_count > 0 || $order_count > 0) {
                    wp_safe_redirect(add_query_arg(array('page'=>'gd-client-portal-tenants','tenant_id'=>$tenant_id,'tenant_error'=>'has_data'), admin_url('admin.php')));
                    exit;
                }

                unset($tenants[$tenant_id]);
                update_option('gd_client_portal_tenants', $tenants);

                $default_tenant_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
                if ($default_tenant_id === $tenant_id) {
                    $remaining = array_keys($tenants);
                    update_option('gd_client_portal_default_tenant_id', !empty($remaining) ? absint($remaining[0]) : 0);
                }
            }

            wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-tenants')));
            exit;
        }
    }
}

if (!function_exists('gd_client_portal_handle_tenant_user_assignment')) {
    function gd_client_portal_handle_tenant_user_assignment()
    {
        if (empty($_POST['gd_client_portal_tenant_user_action'])) return;
        if (!current_user_can('manage_options')) wp_die(esc_html__('Only a platform administrator can manage tenant users.', 'gd-client-portal'));
        if (empty($_POST['gd_client_portal_tenant_users_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_tenant_users_nonce']), 'gd_client_portal_tenant_users')) wp_die(esc_html__('Invalid tenant user request.', 'gd-client-portal'));
        $tenant_id = isset($_POST['tenant_id']) ? absint(wp_unslash($_POST['tenant_id'])) : 0;
        $tenants = gd_client_portal_get_all_tenants();
        if ($tenant_id <= 0 || !isset($tenants[$tenant_id])) wp_die(esc_html__('Invalid tenant.', 'gd-client-portal'));
        $user_ids = isset($_POST['tenant_user_ids']) && is_array($_POST['tenant_user_ids']) ? array_values(array_filter(array_unique(array_map('absint', wp_unslash($_POST['tenant_user_ids']))))) : array();
        foreach (gd_client_portal_get_users_for_tenant($tenant_id) as $existing_user) { if (!in_array((int) $existing_user->ID, $user_ids, true)) gd_client_portal_set_user_tenant($existing_user->ID, 0); }
        foreach ($user_ids as $user_id) { if (gd_client_portal_cached_user($user_id) && !user_can($user_id, 'manage_options')) gd_client_portal_set_user_tenant($user_id, $tenant_id); }
        wp_safe_redirect(add_query_arg(array('page'=>'gd-client-portal-tenants','tenant_id'=>$tenant_id,'settings-updated'=>'1','users-updated'=>'1'), admin_url('admin.php'))); exit;
    }
}

if (!function_exists('gd_client_portal_get_tenant_overview')) {
    /**
     * Build a tenant-scoped command-center summary for the admin Tenants screen.
     * All counts are derived server-side from tenant IDs / project ownership.
     */
    function gd_client_portal_get_tenant_overview($tenant_id)
    {
        global $wpdb;

        $tenant_id = absint($tenant_id);
        $overview = array(
            'users' => 0,
            'projects' => 0,
            'active_projects' => 0,
            'completed_projects' => 0,
            'orders' => 0,
            'messages' => 0,
            'recent_activity' => array(),
        );

        if ($tenant_id <= 0) {
            return $overview;
        }

        $overview['users'] = count(gd_client_portal_get_users_for_tenant($tenant_id));

        $project_table = gd_client_portal_get_project_table_name();
        $overview['projects'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$project_table} WHERE tenant_id = %d",
            $tenant_id
        ));
        $overview['completed_projects'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$project_table} WHERE tenant_id = %d AND (LOWER(status) = 'completed' OR LOWER(current_stage) IN ('completed','final_delivery'))",
            $tenant_id
        ));
        $overview['active_projects'] = max(0, $overview['projects'] - $overview['completed_projects']);

        $order_query = new WP_Query(array(
            'post_type' => 'gd_client_portal_marketplace_order',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(array(
                'key' => '_gd_mp_order_tenant_id',
                'value' => (string) $tenant_id,
                'compare' => '=',
            )),
        ));
        $overview['orders'] = (int) $order_query->found_posts;
        wp_reset_postdata();

        if(function_exists('gdcp_message_service')) {
            $overview['messages'] = gdcp_message_service()->count_for_tenant($tenant_id);
        }

        $recent_projects = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, status, current_stage, created_at FROM {$project_table} WHERE tenant_id = %d ORDER BY id DESC LIMIT 5",
            $tenant_id
        ));
        foreach ((array) $recent_projects as $project) {
            $overview['recent_activity'][] = array(
                'type' => 'project',
                'label' => sprintf(__('Project #%d — %s', 'gd-client-portal'), absint($project->id), sanitize_text_field($project->title)),
                'detail' => ucwords(str_replace('_', ' ', sanitize_text_field($project->status ?: $project->current_stage))),
                'date' => sanitize_text_field($project->created_at),
            );
        }

        $recent_orders = get_posts(array(
            'post_type' => 'gd_client_portal_marketplace_order',
            'post_status' => 'any',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(array(
                'key' => '_gd_mp_order_tenant_id',
                'value' => (string) $tenant_id,
                'compare' => '=',
            )),
        ));
        foreach ((array) $recent_orders as $order) {
            $overview['recent_activity'][] = array(
                'type' => 'order',
                'label' => sprintf(__('Order #%d — %s', 'gd-client-portal'), absint($order->ID), sanitize_text_field($order->post_title)),
                'detail' => sanitize_text_field(get_post_meta($order->ID, '_gd_mp_order_status', true) ?: __('Pending', 'gd-client-portal')),
                'date' => sanitize_text_field($order->post_date),
            );
        }

        usort($overview['recent_activity'], function ($a, $b) {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });
        $overview['recent_activity'] = array_slice($overview['recent_activity'], 0, 8);

        return $overview;
    }
}

if (!function_exists('gd_client_portal_render_tenant_overview')) {
    function gd_client_portal_render_tenant_overview($tenant, $overview, $is_platform_admin = false)
    {
        $tenant_id = absint($tenant['id'] ?? 0);
        ?>
        <div class="gdcp-tenant-overview" style="margin:20px 0 28px;">
            <div style="display:flex;justify-content:space-between;gap:20px;align-items:flex-start;flex-wrap:wrap;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                <div style="display:flex;gap:16px;align-items:center;min-width:260px;">
                    <?php if (!empty($tenant['logo_url'])) : ?>
                        <img src="<?php echo esc_url($tenant['logo_url']); ?>" alt="" style="width:64px;height:64px;object-fit:contain;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;padding:5px;" />
                    <?php else : ?>
                        <div style="width:64px;height:64px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f0f0f1;font-size:24px;font-weight:600;"> <?php echo esc_html(strtoupper(substr($tenant['name'] ?? 'T', 0, 1))); ?> </div>
                    <?php endif; ?>
                    <div>
                        <h2 style="margin:0 0 5px;"><?php echo esc_html($tenant['name'] ?? ''); ?></h2>
                        <div style="color:#646970;">@<?php echo esc_html($tenant['slug'] ?? ''); ?> · <strong><?php echo esc_html(ucfirst($tenant['status'] ?? 'active')); ?></strong></div>
                        <?php if (!empty($tenant['primary_contact'])) : ?><div style="margin-top:6px;"><?php echo esc_html($tenant['primary_contact']); ?></div><?php endif; ?>
                    </div>
                </div>
                <div style="min-width:260px;line-height:1.8;color:#50575e;">
                    <?php if (!empty($tenant['email'])) : ?><div><strong><?php echo esc_html__('Email:', 'gd-client-portal'); ?></strong> <?php echo esc_html($tenant['email']); ?></div><?php endif; ?>
                    <?php if (!empty($tenant['phone'])) : ?><div><strong><?php echo esc_html__('Phone:', 'gd-client-portal'); ?></strong> <?php echo esc_html($tenant['phone']); ?></div><?php endif; ?>
                    <?php if (!empty($tenant['address'])) : ?><div><strong><?php echo esc_html__('Address:', 'gd-client-portal'); ?></strong> <?php echo nl2br(esc_html($tenant['address'])); ?></div><?php endif; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-top:14px;">
                <?php foreach (array(
                    'users' => __('Users', 'gd-client-portal'),
                    'projects' => __('Projects', 'gd-client-portal'),
                    'active_projects' => __('Active projects', 'gd-client-portal'),
                    'completed_projects' => __('Completed', 'gd-client-portal'),
                    'orders' => __('Orders', 'gd-client-portal'),
                    'messages' => __('Messages', 'gd-client-portal'),
                ) as $key => $label) : ?>
                    <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:15px;">
                        <div style="color:#646970;font-size:12px;text-transform:uppercase;letter-spacing:.04em;"><?php echo esc_html($label); ?></div>
                        <div style="font-size:25px;font-weight:600;margin-top:5px;"><?php echo esc_html(number_format_i18n(absint($overview[$key] ?? 0))); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);gap:16px;margin-top:16px;">
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;">
                    <h3 style="margin-top:0;"><?php echo esc_html__('Recent tenant activity', 'gd-client-portal'); ?></h3>
                    <?php if (empty($overview['recent_activity'])) : ?>
                        <p class="description"><?php echo esc_html__('No project or order activity has been recorded for this tenant yet.', 'gd-client-portal'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <tbody>
                            <?php foreach ($overview['recent_activity'] as $activity) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($activity['label']); ?></strong><br><span style="color:#646970;"><?php echo esc_html($activity['detail']); ?></span></td>
                                    <td style="width:180px;color:#646970;"><?php echo esc_html($activity['date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;">
                    <h3 style="margin-top:0;"><?php echo esc_html__('Tenant controls', 'gd-client-portal'); ?></h3>
                    <p class="description"><?php echo esc_html__('Use this tenant as the starting point for user, project, order, and account management.', 'gd-client-portal'); ?></p>
                    <?php if ($is_platform_admin) : ?>
                        <p><a class="button button-primary" href="<?php echo esc_url(add_query_arg(array('action'=>'edit','tenant_id'=>$tenant_id), admin_url('admin.php?page=gd-client-portal-tenants'))); ?>"><?php echo esc_html__('Edit tenant profile', 'gd-client-portal'); ?></a></p>
                        <p><a class="button" href="<?php echo esc_url(add_query_arg(array('tenant_id'=>$tenant_id), admin_url('admin.php?page=gd-client-portal-tenants'))); ?>"><?php echo esc_html__('Manage tenant users', 'gd-client-portal'); ?></a></p>
                        <?php if (($tenant['status'] ?? 'active') === 'active') : ?>
                            <p class="description"><?php echo esc_html__('Deactivation is safer than deleting a tenant because it preserves its users and records.', 'gd-client-portal'); ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="description"><?php echo esc_html__('Tenant profile changes are restricted to platform administrators.', 'gd-client-portal'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_tenants_page')) {
    function gd_client_portal_render_tenants_page()
    {
        if (!gd_client_portal_user_can_access_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        $is_platform_admin = current_user_can('manage_options');
        gd_client_portal_handle_tenant_save();
        gd_client_portal_handle_tenant_user_assignment();

        $tenants = gd_client_portal_get_all_tenants();

        // Tenant administrators must never receive the global tenant list.
        // Restrict their view to the tenant assigned to their user account.
        if (!$is_platform_admin) {
            $current_tenant_id = absint(gd_client_portal_get_current_tenant_id());
            if ($current_tenant_id > 0 && isset($tenants[$current_tenant_id])) {
                $tenants = array($current_tenant_id => $tenants[$current_tenant_id]);
            } else {
                $tenants = array();
            }
        }
        $overview_tenant_id = !empty($_GET['tenant_id']) ? absint($_GET['tenant_id']) : 0;
        if (!$is_platform_admin) {
            $overview_tenant_id = absint(gd_client_portal_get_current_tenant_id());
        }
        $overview_tenant = ($overview_tenant_id > 0 && isset($tenants[$overview_tenant_id])) ? $tenants[$overview_tenant_id] : array();
        $overview = !empty($overview_tenant) ? gd_client_portal_get_tenant_overview($overview_tenant_id) : array();
        $default_tenant_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
        $editing = false;
        $tenant = array(
            'id' => 0, 'name' => '', 'slug' => '', 'status' => 'active', 'logo_url' => '',
            'primary_contact' => '', 'email' => '', 'phone' => '', 'address' => '', 'created_at' => '',
        );

        if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['tenant_id'])) {
            $tenant_id = absint($_GET['tenant_id']);
            if (isset($tenants[$tenant_id])) {
                $editing = true;
                $tenant = $tenants[$tenant_id];
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Tenants', 'gd-client-portal'); ?></h1>
            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="updated notice"><p><?php echo esc_html__('Tenant saved.', 'gd-client-portal'); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['tenant_error']) && $_GET['tenant_error'] === 'has_data') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('This tenant cannot be deleted because it still has users, projects, or orders. Deactivate it or reassign its records first.', 'gd-client-portal'); ?></p></div>
            <?php endif; ?>

            <div style="max-width:1100px;">
                <?php if (!empty($overview_tenant)) : ?>
                    <h2 style="margin-top:12px;"><?php echo esc_html__('Tenant overview', 'gd-client-portal'); ?></h2>
                    <?php gd_client_portal_render_tenant_overview($overview_tenant, $overview, $is_platform_admin); ?>
                <?php endif; ?>
                <?php if ($is_platform_admin) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-tenants')); ?>" style="margin-top:12px;">
                        <?php wp_nonce_field('gd_client_portal_tenant_save', 'gd_client_portal_tenant_nonce'); ?>
                        <input type="hidden" name="gd_client_portal_tenant_action" value="<?php echo esc_attr($editing ? 'update' : 'add'); ?>" />
                        <?php if ($editing) : ?>
                            <input type="hidden" name="tenant_id" value="<?php echo esc_attr($tenant['id']); ?>" />
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="tenant_name"><?php echo esc_html__('Tenant name', 'gd-client-portal'); ?></label></th>
                                <td><input type="text" id="tenant_name" name="tenant_name" value="<?php echo esc_attr($tenant['name']); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="tenant_slug"><?php echo esc_html__('Tenant slug', 'gd-client-portal'); ?></label></th>
                                <td><input type="text" id="tenant_slug" name="tenant_slug" value="<?php echo esc_attr($tenant['slug']); ?>" class="regular-text" /></td>
                            </tr>
                            <tr><th scope="row"><label for="tenant_status"><?php echo esc_html__('Status', 'gd-client-portal'); ?></label></th><td><select id="tenant_status" name="tenant_status"><option value="active" <?php selected($tenant['status'], 'active'); ?>><?php echo esc_html__('Active', 'gd-client-portal'); ?></option><option value="inactive" <?php selected($tenant['status'], 'inactive'); ?>><?php echo esc_html__('Inactive', 'gd-client-portal'); ?></option></select></td></tr>
                            <tr><th scope="row"><label for="tenant_logo_url"><?php echo esc_html__('Logo URL', 'gd-client-portal'); ?></label></th><td><input type="url" id="tenant_logo_url" name="tenant_logo_url" value="<?php echo esc_attr($tenant['logo_url']); ?>" class="regular-text" /></td></tr>
                            <tr><th scope="row"><label for="tenant_primary_contact"><?php echo esc_html__('Primary contact', 'gd-client-portal'); ?></label></th><td><input type="text" id="tenant_primary_contact" name="tenant_primary_contact" value="<?php echo esc_attr($tenant['primary_contact']); ?>" class="regular-text" /></td></tr>
                            <tr><th scope="row"><label for="tenant_email"><?php echo esc_html__('Email', 'gd-client-portal'); ?></label></th><td><input type="email" id="tenant_email" name="tenant_email" value="<?php echo esc_attr($tenant['email']); ?>" class="regular-text" /></td></tr>
                            <tr><th scope="row"><label for="tenant_phone"><?php echo esc_html__('Phone', 'gd-client-portal'); ?></label></th><td><input type="text" id="tenant_phone" name="tenant_phone" value="<?php echo esc_attr($tenant['phone']); ?>" class="regular-text" /></td></tr>
                            <tr><th scope="row"><label for="tenant_address"><?php echo esc_html__('Address', 'gd-client-portal'); ?></label></th><td><textarea id="tenant_address" name="tenant_address" rows="3" class="large-text"><?php echo esc_textarea($tenant['address']); ?></textarea></td></tr>
                        </table>
                        <?php submit_button($editing ? __('Update tenant', 'gd-client-portal') : __('Add tenant', 'gd-client-portal')); ?>
                    </form>
                <?php else : ?>
                    <div class="notice notice-info inline"><p><?php echo esc_html__('You are viewing your assigned tenant. Tenant records are managed by a platform administrator.', 'gd-client-portal'); ?></p></div>
                <?php endif; ?>

                <?php if ($is_platform_admin && !empty($_GET['tenant_id']) && isset($tenants[absint($_GET['tenant_id'])])) : ?>
                    <?php $managed_tenant_id = absint($_GET['tenant_id']); $assigned_ids = array_map('intval', wp_list_pluck(gd_client_portal_get_users_for_tenant($managed_tenant_id), 'ID')); $all_users = get_users(array('number'=>-1,'orderby'=>'display_name','order'=>'ASC')); ?>
                    <hr style="margin:28px 0;" />
                    <h2><?php echo esc_html__('Tenant users', 'gd-client-portal'); ?></h2>
                    <p class="description"><?php echo esc_html__('Select the users who belong to this tenant. Platform administrators are global and are excluded.', 'gd-client-portal'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-tenants')); ?>">
                        <?php wp_nonce_field('gd_client_portal_tenant_users', 'gd_client_portal_tenant_users_nonce'); ?>
                        <input type="hidden" name="gd_client_portal_tenant_user_action" value="save" /><input type="hidden" name="tenant_id" value="<?php echo esc_attr($managed_tenant_id); ?>" />
                        <div style="max-width:1100px;max-height:360px;overflow:auto;border:1px solid #dcdcde;padding:8px;background:#fff;">
                        <?php foreach ($all_users as $candidate) : if (user_can($candidate->ID, 'manage_options')) continue; ?>
                            <label style="display:block;padding:7px 4px;border-bottom:1px solid #f0f0f1;"><input type="checkbox" name="tenant_user_ids[]" value="<?php echo esc_attr($candidate->ID); ?>" <?php checked(in_array((int)$candidate->ID, $assigned_ids, true)); ?> /> <strong><?php echo esc_html($candidate->display_name ?: $candidate->user_login); ?></strong> <span style="color:#646970;">— <?php echo esc_html($candidate->user_email); ?></span></label>
                        <?php endforeach; ?>
                        </div>
                        <?php submit_button(__('Save tenant users', 'gd-client-portal'), 'primary', 'submit', false, array('style'=>'margin-top:10px;')); ?>
                    </form>
                <?php endif; ?>

                <h2><?php echo esc_html__('Existing tenants', 'gd-client-portal'); ?></h2>
                <?php if ($is_platform_admin) : ?>
                    <p class="description"><?php echo esc_html__('Assign users to a tenant from Users → Edit User. Tenant administrators will then be automatically scoped to that tenant across portal projects, messages, deliverables, and marketplace order records.', 'gd-client-portal'); ?></p>
                <?php endif; ?>
                <?php if (empty($tenants)) : ?>
                    <p><?php echo esc_html__('No tenants created yet.', 'gd-client-portal'); ?></p>
                <?php else : ?>
                    <table class="widefat fixed" style="max-width:1100px; margin-top:12px;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Name', 'gd-client-portal'); ?></th>
                                <th><?php echo esc_html__('Slug', 'gd-client-portal'); ?></th>
                                <th><?php echo esc_html__('Status', 'gd-client-portal'); ?></th>
                                <th><?php echo esc_html__('Users', 'gd-client-portal'); ?></th>
                                <th><?php echo esc_html__('Default', 'gd-client-portal'); ?></th>
                                <th><?php echo esc_html__('Actions', 'gd-client-portal'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenants as $tenant_item) : ?>
                                <?php $tenant_id_value = absint($tenant_item['id'] ?? 0); ?>
                                <tr>
                                    <td><?php echo esc_html($tenant_item['name'] ?? ''); ?></td>
                                    <td><?php echo esc_html($tenant_item['slug'] ?? ''); ?></td>
                                    <td><?php echo ($tenant_item['status'] ?? 'active') === 'active' ? esc_html__('Active', 'gd-client-portal') : esc_html__('Inactive', 'gd-client-portal'); ?></td>
                                    <td><?php echo esc_html(count(gd_client_portal_get_users_for_tenant($tenant_id_value))); ?></td>
                                    <td><?php echo $default_tenant_id === $tenant_id_value ? esc_html__('Yes', 'gd-client-portal') : esc_html__('No', 'gd-client-portal'); ?></td>
                                    <td>
                                        <?php if ($is_platform_admin) : ?>
                                            <a class="button" href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'tenant_id' => $tenant_id_value), admin_url('admin.php?page=gd-client-portal-tenants'))); ?>"><?php echo esc_html__('Edit', 'gd-client-portal'); ?></a>
                                            <a class="button" href="<?php echo esc_url(add_query_arg(array('tenant_id' => $tenant_id_value), admin_url('admin.php?page=gd-client-portal-tenants'))); ?>"><?php echo esc_html__('Manage users', 'gd-client-portal'); ?></a>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-tenants')); ?>" style="display:inline-block; margin-left:6px;">
                                                <?php wp_nonce_field('gd_client_portal_tenant_save', 'gd_client_portal_tenant_nonce'); ?>
                                                <input type="hidden" name="gd_client_portal_tenant_action" value="delete" />
                                                <input type="hidden" name="tenant_id" value="<?php echo esc_attr($tenant_id_value); ?>" />
                                                <button class="button" type="submit" onclick="return confirm('<?php echo esc_js(__('Delete this tenant?', 'gd-client-portal')); ?>')"><?php echo esc_html__('Delete', 'gd-client-portal'); ?></button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-settings')); ?>" style="display:inline-block; margin-left:6px;">
                                                <?php wp_nonce_field('gd_client_portal_save_settings', 'gd_client_portal_settings_nonce'); ?>
                                                <input type="hidden" name="gd_client_portal_save_settings" value="1" />
                                                <input type="hidden" name="gd_client_portal_default_tenant_id" value="<?php echo esc_attr($tenant_id_value); ?>" />
                                                <button class="button button-secondary" type="submit"><?php echo esc_html__('Set as default', 'gd-client-portal'); ?></button>
                                            </form>
                                        <?php else : ?>
                                            <?php echo esc_html__('Read only', 'gd-client-portal'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_tenant_selector')) {
    function gd_client_portal_render_tenant_selector($selected = 0, $field_name = 'gd_client_portal_tenant_id')
    {
        $tenants = gd_client_portal_get_all_tenants();
        if (empty($tenants)) {
            echo '<p class="description">' . esc_html__('No tenants configured yet. Set a default tenant below or create a tenant record in code.', 'gd-client-portal') . '</p>';
            return;
        }

        echo '<select name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '">';
        echo '<option value="0">' . esc_html__('Unassigned', 'gd-client-portal') . '</option>';
        foreach ($tenants as $tenant) {
            $tenant_id = absint($tenant['id'] ?? 0);
            $label = sanitize_text_field($tenant['name'] ?? '');
            if ($tenant_id <= 0 || $label === '') {
                continue;
            }
            echo '<option value="' . esc_attr($tenant_id) . '" ' . selected($selected, $tenant_id, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }
}

if (!function_exists('gd_client_portal_add_tenant_fields_to_user_profile')) {
    function gd_client_portal_add_tenant_fields_to_user_profile($user)
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $selected = absint(get_user_meta($user->ID, 'gd_client_portal_tenant_id', true));
        ?>
        <h3><?php echo esc_html__('Client portal tenant', 'gd-client-portal'); ?></h3>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="gd_client_portal_tenant_id"><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></label></th>
                <td>
                    <?php gd_client_portal_render_tenant_selector($selected, 'gd_client_portal_tenant_id'); ?>
                    <p class="description"><?php echo esc_html__('Assign this user to a tenant so the marketplace and dashboard data stay scoped to the correct client.', 'gd-client-portal'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="gd_client_portal_is_tenant_admin"><?php echo esc_html__('Tenant admin', 'gd-client-portal'); ?></label></th>
                <td>
                    <?php $is_tadmin = user_can($user->ID, 'gd_client_portal_tenant_admin'); ?>
                    <label><input name="gd_client_portal_is_tenant_admin" type="checkbox" value="1" <?php checked($is_tadmin); ?> /> <?php echo esc_html__('Grant tenant administration privileges for the assigned tenant', 'gd-client-portal'); ?></label>
                    <p class="description"><?php echo esc_html__('Users with this capability can manage tenant-specific content for their assigned tenant.', 'gd-client-portal'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    add_action('show_user_profile', 'gd_client_portal_add_tenant_fields_to_user_profile');
    add_action('edit_user_profile', 'gd_client_portal_add_tenant_fields_to_user_profile');
}

if (!function_exists('gd_client_portal_save_tenant_fields_to_user_profile')) {
    function gd_client_portal_save_tenant_fields_to_user_profile($user_id)
    {
        // The capability must be checked against the CURRENT editor, not the
        // target user. Otherwise platform admins cannot assign ordinary users.
        if (!current_user_can('manage_options') || !current_user_can('edit_user', $user_id)) {
            return;
        }

        if (!isset($_POST['gd_client_portal_tenant_id'])) {
            return;
        }

        $tenant_id = absint(wp_unslash($_POST['gd_client_portal_tenant_id']));
        $valid_tenants = gd_client_portal_get_all_tenants();
        if ($tenant_id > 0 && !isset($valid_tenants[$tenant_id])) {
            $tenant_id = 0;
        }
        gd_client_portal_set_user_tenant($user_id, $tenant_id);

        // Save tenant admin flag and add/remove capability
        $is_tadmin = !empty($_POST['gd_client_portal_is_tenant_admin']) ? 1 : 0;
        update_user_meta($user_id, 'gd_client_portal_is_tenant_admin', $is_tadmin);
        $user = new WP_User($user_id);
        if ($is_tadmin) {
            if (!$user->has_cap('gd_client_portal_tenant_admin')) {
                $user->add_cap('gd_client_portal_tenant_admin');
            }
        } else {
            if ($user->has_cap('gd_client_portal_tenant_admin')) {
                $user->remove_cap('gd_client_portal_tenant_admin');
            }
        }
    }
    add_action('personal_options_update', 'gd_client_portal_save_tenant_fields_to_user_profile');
    add_action('edit_user_profile_update', 'gd_client_portal_save_tenant_fields_to_user_profile');
}


if (!function_exists('gd_client_portal_module_log')) {
    /**
     * Append an entry to the module activation log.
     *
     * @param string $slug Module slug
     * @param string $action Action name (activate|deactivate)
     * @param string $status Result status (success|error|started)
     * @param string $message Optional message or error
     */
    function gd_client_portal_module_log($slug, $action, $status = 'success', $message = '')
    {
        $slug = sanitize_text_field($slug);
        $action = sanitize_text_field($action);
        $status = sanitize_text_field($status);
        $message = sanitize_text_field($message);

        $log = get_option('gd_client_portal_module_log', array());
        if (!is_array($log)) {
            $log = array();
        }

        $log[] = array(
            'time' => current_time('mysql'),
            'slug' => $slug,
            'action' => $action,
            'status' => $status,
            'message' => $message,
        );

        // Keep only the most recent 200 entries
        if (count($log) > 200) {
            $log = array_slice($log, -200);
        }

        update_option('gd_client_portal_module_log', $log);
    }
}

if (!function_exists('gd_client_portal_get_module_log')) {
    function gd_client_portal_get_module_log()
    {
        $log = get_option('gd_client_portal_module_log', array());
        return is_array($log) ? $log : array();
    }
}

if (!function_exists('gd_client_portal_handle_settings_save')) {
    function gd_client_portal_handle_settings_save()
    {
        if (!isset($_POST['gd_client_portal_save_settings'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        if (!isset($_POST['gd_client_portal_settings_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_settings_nonce']), 'gd_client_portal_save_settings')) {
            wp_die(esc_html__('Invalid request.', 'gd-client-portal'));
        }

        $dashboard_title = isset($_POST['gd_client_portal_dashboard_title']) ? sanitize_text_field(wp_unslash($_POST['gd_client_portal_dashboard_title'])) : '';
        $welcome_message = isset($_POST['gd_client_portal_welcome_message']) ? sanitize_text_field(wp_unslash($_POST['gd_client_portal_welcome_message'])) : '';
        $enable_sections = !empty($_POST['gd_client_portal_enable_sections']) ? '1' : '0';
        $woo_redirect = !empty($_POST['gd_client_portal_woo_redirect_after_service']) ? '1' : '0';
        $woo_automation = !empty($_POST['gd_client_portal_woo_automation_enabled']) ? '1' : '0';
        $auth_page_id = isset($_POST['gd_client_portal_auth_page_id']) ? absint(wp_unslash($_POST['gd_client_portal_auth_page_id'])) : 0;
        $dashboard_page_id = isset($_POST['gd_client_portal_dashboard_page_id']) ? absint(wp_unslash($_POST['gd_client_portal_dashboard_page_id'])) : 0;
        $paystack_enabled = !empty($_POST['gd_client_portal_paystack_enabled']) ? '1' : '0';
        $paystack_currency = isset($_POST['gd_client_portal_paystack_currency']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['gd_client_portal_paystack_currency']))) : 'USD';
        $paystack_public_key = isset($_POST['gd_client_portal_paystack_public_key']) ? sanitize_text_field(wp_unslash($_POST['gd_client_portal_paystack_public_key'])) : '';
        $paystack_secret_key = isset($_POST['gd_client_portal_paystack_secret_key']) ? sanitize_text_field(wp_unslash($_POST['gd_client_portal_paystack_secret_key'])) : '';
        $paystack_webhook_secret = isset($_POST['gd_client_portal_paystack_webhook_secret']) ? sanitize_text_field(wp_unslash($_POST['gd_client_portal_paystack_webhook_secret'])) : '';
        $default_tenant_id = isset($_POST['gd_client_portal_default_tenant_id']) ? absint(wp_unslash($_POST['gd_client_portal_default_tenant_id'])) : 0;

        update_option('gd_client_portal_dashboard_title', $dashboard_title);
        update_option('gd_client_portal_welcome_message', $welcome_message);
        update_option('gd_client_portal_enable_sections', $enable_sections);
        update_option('gd_client_portal_woo_redirect_after_service', $woo_redirect);
        update_option('gd_client_portal_woo_automation_enabled', $woo_automation);
        update_option('gd_client_portal_auth_page_id', $auth_page_id);
        update_option('gd_client_portal_dashboard_page_id', $dashboard_page_id);
        update_option('gd_client_portal_default_tenant_id', $default_tenant_id);
        update_option('gd_client_portal_paystack_enabled', $paystack_enabled);
        update_option('gd_client_portal_paystack_currency', in_array($paystack_currency, array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'EUR', 'GBP'), true) ? $paystack_currency : 'USD');
        update_option('gd_client_portal_paystack_public_key', $paystack_public_key);
        update_option('gd_client_portal_paystack_secret_key', $paystack_secret_key);
        update_option('gd_client_portal_paystack_webhook_secret', $paystack_webhook_secret);

        if ($auth_page_id > 0) {
            update_option('gd_client_portal_auth_page_url', get_permalink($auth_page_id));
        } else {
            delete_option('gd_client_portal_auth_page_url');
        }

        if ($dashboard_page_id > 0) {
            update_option('gd_client_portal_dashboard_page_url', get_permalink($dashboard_page_id));
        } else {
            delete_option('gd_client_portal_dashboard_page_url');
        }

        // Handle enabled modules list (array of slugs)
        $enabled_modules = isset($_POST['gd_client_portal_enabled_modules']) && is_array($_POST['gd_client_portal_enabled_modules']) ? array_map('sanitize_text_field', wp_unslash($_POST['gd_client_portal_enabled_modules'])) : array();

        // Determine previous enabled modules so we can run activation/deactivation hooks
        $previous_enabled = get_option('gd_client_portal_enabled_modules', array());
        $previous_enabled = is_array($previous_enabled) ? $previous_enabled : array();

        // Persist the new list
        update_option('gd_client_portal_enabled_modules', $enabled_modules);

        // Compute changes
        $to_enable = array_values(array_diff($enabled_modules, $previous_enabled));
        $to_disable = array_values(array_diff($previous_enabled, $enabled_modules));

        // Run per-module activate hooks for newly enabled modules
        foreach ($to_enable as $slug) {
            $slug = sanitize_file_name($slug);
            $module_index = GD_CLIENT_PORTAL_PATH . 'modules/' . $slug . '/index.php';
            if (is_file($module_index)) {
                // load module to ensure functions are available
                require_once $module_index;
                $activate_fn = 'gd_client_portal_module_' . $slug . '_activate';
                if (function_exists($activate_fn)) {
                    // record start
                    gd_client_portal_module_log($slug, 'activate', 'started');
                    try {
                        call_user_func($activate_fn);
                        gd_client_portal_module_log($slug, 'activate', 'success');
                    } catch (Exception $e) {
                        gd_client_portal_module_log($slug, 'activate', 'error', $e->getMessage());
                    }
                }
            }
        }

        // Run per-module deactivate hooks for newly disabled modules
        foreach ($to_disable as $slug) {
            $slug = sanitize_file_name($slug);
            $module_index = GD_CLIENT_PORTAL_PATH . 'modules/' . $slug . '/index.php';
            if (is_file($module_index)) {
                // load module file in case it defines a deactivate function
                require_once $module_index;
                $deactivate_fn = 'gd_client_portal_module_' . $slug . '_deactivate';
                if (function_exists($deactivate_fn)) {
                    gd_client_portal_module_log($slug, 'deactivate', 'started');
                    try {
                        call_user_func($deactivate_fn);
                        gd_client_portal_module_log($slug, 'deactivate', 'success');
                    } catch (Exception $e) {
                        gd_client_portal_module_log($slug, 'deactivate', 'error', $e->getMessage());
                    }
                }
            }
        }

        wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-settings')));
        exit;
    }
}

if (!function_exists('gd_client_portal_render_admin_page')) {
    function gd_client_portal_render_admin_page()
    {
        if (!gd_client_portal_user_can_access_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        $dashboard_title = get_option('gd_client_portal_dashboard_title', __('Client Dashboard', 'gd-client-portal'));
        $welcome_message = get_option('gd_client_portal_welcome_message', __('Welcome to your client portal.', 'gd-client-portal'));
        $enable_sections = get_option('gd_client_portal_enable_sections', '1');
        $woo_redirect = get_option('gd_client_portal_woo_redirect_after_service', '0');
        $woo_automation = get_option('gd_client_portal_woo_automation_enabled', '1');
        $auth_page_url = gd_client_portal_get_redirect_page_url('gd_client_portal_auth_page_id', 'gd_client_portal_auth_page_url');
        $dashboard_page_url = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
        $woocommerce_active = gd_client_portal_is_woocommerce_active();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('GD Client Portal', 'gd-client-portal'); ?></h1>
            <p><?php echo esc_html__('The portal plugin is active and ready to use.', 'gd-client-portal'); ?></p>

            <div class="card" style="max-width: 980px; padding: 20px; margin-top: 20px;">
                <h2><?php echo esc_html__('Portal Overview', 'gd-client-portal'); ?></h2>
                <p><strong><?php echo esc_html__('Dashboard title:', 'gd-client-portal'); ?></strong> <?php echo esc_html($dashboard_title); ?></p>
                <p><strong><?php echo esc_html__('Welcome message:', 'gd-client-portal'); ?></strong> <?php echo esc_html($welcome_message); ?></p>
                <p><strong><?php echo esc_html__('Dashboard sections:', 'gd-client-portal'); ?></strong> <?php echo $enable_sections === '1' ? esc_html__('Enabled', 'gd-client-portal') : esc_html__('Disabled', 'gd-client-portal'); ?></p>
                <p><strong><?php echo esc_html__('WooCommerce status:', 'gd-client-portal'); ?></strong> <?php echo $woocommerce_active ? esc_html__('Active', 'gd-client-portal') : esc_html__('Not active', 'gd-client-portal'); ?></p>
                    <p><strong><?php echo esc_html__('Redirect after service purchase:', 'gd-client-portal'); ?></strong> <?php echo $woo_redirect === '1' ? esc_html__('Enabled', 'gd-client-portal') : esc_html__('Disabled', 'gd-client-portal'); ?></p>
                    <p><strong><?php echo esc_html__('Auth page URL:', 'gd-client-portal'); ?></strong> <?php echo esc_html($auth_page_url ?: __('Not configured', 'gd-client-portal')); ?></p>
                    <p><strong><?php echo esc_html__('Dashboard page URL:', 'gd-client-portal'); ?></strong> <?php echo esc_html($dashboard_page_url ?: __('Not configured', 'gd-client-portal')); ?></p>
            </div>

            <div class="card" style="max-width: 980px; padding: 20px; margin-top: 20px;">
                <h2><?php echo esc_html__('Available shortcodes', 'gd-client-portal'); ?></h2>
                <?php
                global $shortcode_tags;
                $found = array();
                if (!empty($shortcode_tags) && is_array($shortcode_tags)) {
                    foreach ($shortcode_tags as $tag => $callable) {
                        $call_name = '';
                        if (is_string($callable)) {
                            $call_name = $callable;
                        } elseif (is_array($callable)) {
                            if (is_object($callable[0])) {
                                $call_name = get_class($callable[0]) . '::' . $callable[1];
                            } else {
                                $call_name = $callable[0] . '::' . $callable[1];
                            }
                        } else {
                            $call_name = 'closure';
                        }

                        // Heuristic: list shortcodes that appear to belong to this plugin
                        if (strpos($tag, 'gd_') === 0 || strpos($tag, 'gdclient') === 0 || strpos($tag, 'gd_client_portal') !== false || strpos($call_name, 'gd_client_portal_') !== false) {
                            $found[$tag] = $call_name;
                        }
                    }
                }

                if (empty($found)) : ?>
                    <p><?php echo esc_html__('No plugin shortcodes discovered.', 'gd-client-portal'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($found as $tag => $callable_name) : ?>
                            <li><code>[<?php echo esc_html($tag); ?>]</code> — <?php echo esc_html($callable_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 980px; padding: 20px; margin-top: 20px;">
                <h2><?php echo esc_html__('Registered Dashboard Views', 'gd-client-portal'); ?></h2>
                <p><?php echo esc_html__('List of modules registered to provide a dashboard detail view.', 'gd-client-portal'); ?></p>
                <?php
                $registered = function_exists('gd_client_portal_get_all_registered_dashboard_views') ? gd_client_portal_get_all_registered_dashboard_views() : array();
                if (empty($registered)) : ?>
                    <p><?php echo esc_html__('No dashboard views registered yet.', 'gd-client-portal'); ?></p>
                <?php else : ?>
                    <table class="widefat" style="max-width:980px;">
                        <thead><tr><th><?php echo esc_html__('Module', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Callable', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Preview', 'gd-client-portal'); ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($registered as $mod => $callable) :
                            $call_name = '';
                            if (is_string($callable)) {
                                $call_name = $callable;
                            } elseif (is_array($callable)) {
                                if (is_object($callable[0])) {
                                    $call_name = get_class($callable[0]) . '::' . $callable[1];
                                } else {
                                    $call_name = $callable[0] . '::' . $callable[1];
                                }
                            } elseif ($callable instanceof Closure || is_object($callable)) {
                                $call_name = esc_html__('closure', 'gd-client-portal');
                            } else {
                                $call_name = esc_html__('callable', 'gd-client-portal');
                            }
                        ?>
                            <?php
                                $dashboard_url = $dashboard_page_url ?: (function_exists('gd_client_portal_get_dashboard_url') ? gd_client_portal_get_dashboard_url() : home_url('/dashboard/'));
                                $preview_url = esc_url(add_query_arg(array('module' => $mod, 'preview' => '1'), $dashboard_url));
                            ?>
                            <tr>
                                <td><?php echo esc_html($mod); ?></td>
                                <td><?php echo esc_html($call_name); ?></td>
                                <td><a class="button" href="<?php echo $preview_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Preview', 'gd-client-portal'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 980px; padding: 20px; margin-top: 20px;">
                <h2><?php echo esc_html__('Next steps', 'gd-client-portal'); ?></h2>
                <p><?php echo esc_html__('Add the dashboard shortcode to a page or template to expose the portal UI to logged-in clients.', 'gd-client-portal'); ?></p>
                <?php if (current_user_can('manage_options')) : ?>
                    <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-settings')); ?>"><?php echo esc_html__('Open settings', 'gd-client-portal'); ?></a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_settings_page')) {
    function gd_client_portal_render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        gd_client_portal_handle_settings_save();

        $dashboard_title = get_option('gd_client_portal_dashboard_title', __('Client Dashboard', 'gd-client-portal'));
        $welcome_message = get_option('gd_client_portal_welcome_message', __('Welcome to your client portal.', 'gd-client-portal'));
        $enable_sections = get_option('gd_client_portal_enable_sections', '1');
        $woo_redirect = get_option('gd_client_portal_woo_redirect_after_service', '0');
        $auth_page_id = absint(get_option('gd_client_portal_auth_page_id', 0));
        $dashboard_page_id = absint(get_option('gd_client_portal_dashboard_page_id', 0));
        $default_tenant_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
        $paystack_enabled = get_option('gd_client_portal_paystack_enabled', '0');
        $paystack_currency = strtoupper(get_option('gd_client_portal_paystack_currency', 'USD'));
        $paystack_public_key = get_option('gd_client_portal_paystack_public_key', '');
        $paystack_secret_key = get_option('gd_client_portal_paystack_secret_key', '');
        $paystack_webhook_secret = get_option('gd_client_portal_paystack_webhook_secret', '');
        $enabled_modules = get_option('gd_client_portal_enabled_modules', array());

        // Discover available modules in the modules/ folder
        $modules = array();
        $modules_dir = GD_CLIENT_PORTAL_PATH . 'modules/';
        if (is_dir($modules_dir)) {
            foreach (glob($modules_dir . '*', GLOB_ONLYDIR) as $modpath) {
                $slug = basename($modpath);
                $index = $modpath . '/index.php';
                $title = $slug;
                $desc = '';
                if (is_file($index)) {
                    // Prefer module.json metadata if present
                    $meta_file = $modpath . '/module.json';
                    if (is_file($meta_file)) {
                        $json = json_decode(file_get_contents($meta_file), true);
                        if (is_array($json)) {
                            if (!empty($json['title'])) {
                                $title = $json['title'];
                            }
                            if (!empty($json['description'])) {
                                $desc = $json['description'];
                            }
                        }
                    } else {
                        // fallback: attempt to read a short docblock or first comment line for description
                        $content = file_get_contents($index);
                        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $m)) {
                            $desc = strip_tags(trim(preg_replace('/\s+\*/', '', $m[1] ?? '')));
                        }
                    }
                }
                $modules[] = array(
                    'slug' => $slug,
                    'path' => $modpath,
                    'title' => $title,
                    'description' => $desc,
                    'enabled' => in_array($slug, (array) $enabled_modules, true),
                );
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('GD Client Portal Settings', 'gd-client-portal'); ?></h1>
            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="updated notice"><p><?php echo esc_html__('Settings saved.', 'gd-client-portal'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-settings')); ?>">
                <?php wp_nonce_field('gd_client_portal_save_settings', 'gd_client_portal_settings_nonce'); ?>
                <input type="hidden" name="gd_client_portal_save_settings" value="1" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="gd_client_portal_dashboard_title"><?php echo esc_html__('Dashboard title', 'gd-client-portal'); ?></label></th>
                        <td><input name="gd_client_portal_dashboard_title" type="text" id="gd_client_portal_dashboard_title" value="<?php echo esc_attr($dashboard_title); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_welcome_message"><?php echo esc_html__('Welcome message', 'gd-client-portal'); ?></label></th>
                        <td><input name="gd_client_portal_welcome_message" type="text" id="gd_client_portal_welcome_message" value="<?php echo esc_attr($welcome_message); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_enable_sections"><?php echo esc_html__('Enable dashboard sections', 'gd-client-portal'); ?></label></th>
                        <td>
                            <label>
                                <input name="gd_client_portal_enable_sections" type="checkbox" id="gd_client_portal_enable_sections" value="1" <?php checked($enable_sections, '1'); ?> />
                                <?php echo esc_html__('Show the grouped dashboard card layout', 'gd-client-portal'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_woo_automation_enabled"><?php echo esc_html__('WooCommerce Automation 2.0', 'gd-client-portal'); ?></label></th>
                        <td>
                            <label>
                                <input name="gd_client_portal_woo_automation_enabled" type="checkbox" id="gd_client_portal_woo_automation_enabled" value="1" <?php checked($woo_automation, '1'); ?> />
                                <?php echo esc_html__('Automatically turn paid service purchases into tenant-scoped projects and move them into the requirements → workflow → approval → delivery lifecycle.', 'gd-client-portal'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Project creation is idempotent, uses the order/user/default tenant boundary, and can claim eligible guest purchases when the customer registers.', 'gd-client-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_woo_redirect_after_service"><?php echo esc_html__('Redirect after WooCommerce service purchase', 'gd-client-portal'); ?></label></th>
                        <td>
                            <label>
                                <input name="gd_client_portal_woo_redirect_after_service" type="checkbox" id="gd_client_portal_woo_redirect_after_service" value="1" <?php checked($woo_redirect, '1'); ?> />
                                <?php echo esc_html__('When enabled, users who purchase a service will be redirected to the configured auth or dashboard page.', 'gd-client-portal'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_auth_page_id"><?php echo esc_html__('Auth page', 'gd-client-portal'); ?></label></th>
                        <td>
                            <?php echo wp_dropdown_pages(array(
                                'name' => 'gd_client_portal_auth_page_id',
                                'id' => 'gd_client_portal_auth_page_id',
                                'selected' => $auth_page_id,
                                'show_option_none' => __('Select a page', 'gd-client-portal'),
                                'echo' => false,
                            )); ?>
                            <p class="description"><?php echo esc_html__('Choose a page containing the [gd_client_portal_auth] shortcode for guest post-purchase registration.', 'gd-client-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_dashboard_page_id"><?php echo esc_html__('Dashboard page', 'gd-client-portal'); ?></label></th>
                        <td>
                            <?php echo wp_dropdown_pages(array(
                                'name' => 'gd_client_portal_dashboard_page_id',
                                'id' => 'gd_client_portal_dashboard_page_id',
                                'selected' => $dashboard_page_id,
                                'show_option_none' => __('Select a page', 'gd-client-portal'),
                                'echo' => false,
                            )); ?>
                            <p class="description"><?php echo esc_html__('Choose the public dashboard page containing the [gd_client_portal_dashboard] shortcode.', 'gd-client-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_default_tenant_id"><?php echo esc_html__('Default tenant', 'gd-client-portal'); ?></label></th>
                        <td>
                            <?php gd_client_portal_render_tenant_selector($default_tenant_id, 'gd_client_portal_default_tenant_id'); ?>
                            <p class="description"><?php echo esc_html__('Used as the fallback tenant for new or unassigned users in the multi-tenant marketplace flow.', 'gd-client-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_paystack_enabled"><?php echo esc_html__('Use Paystack checkout', 'gd-client-portal'); ?></label></th>
                        <td>
                            <label>
                                <input name="gd_client_portal_paystack_enabled" type="checkbox" id="gd_client_portal_paystack_enabled" value="1" <?php checked($paystack_enabled, '1'); ?> />
                                <?php echo esc_html__('Enable hosted Paystack checkout for marketplace purchases.', 'gd-client-portal'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_paystack_currency"><?php echo esc_html__('Paystack currency', 'gd-client-portal'); ?></label></th>
                        <td>
                            <select name="gd_client_portal_paystack_currency" id="gd_client_portal_paystack_currency">
                                <?php foreach (array('USD','NGN','GHS','ZAR','KES','EUR','GBP') as $currency) : ?>
                                    <option value="<?php echo esc_attr($currency); ?>" <?php selected($paystack_currency, $currency); ?>><?php echo esc_html($currency); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Select the currency supported by your Paystack merchant account. NGN may fail if your account is not configured for it.', 'gd-client-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_paystack_public_key"><?php echo esc_html__('Paystack public key', 'gd-client-portal'); ?></label></th>
                        <td><input name="gd_client_portal_paystack_public_key" type="text" id="gd_client_portal_paystack_public_key" value="<?php echo esc_attr($paystack_public_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_paystack_secret_key"><?php echo esc_html__('Paystack secret key', 'gd-client-portal'); ?></label></th>
                        <td><input name="gd_client_portal_paystack_secret_key" type="password" id="gd_client_portal_paystack_secret_key" value="<?php echo esc_attr($paystack_secret_key); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="gd_client_portal_paystack_webhook_secret"><?php echo esc_html__('Paystack webhook secret', 'gd-client-portal'); ?></label></th>
                        <td><input name="gd_client_portal_paystack_webhook_secret" type="password" id="gd_client_portal_paystack_webhook_secret" value="<?php echo esc_attr($paystack_webhook_secret); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Module Log', 'gd-client-portal'); ?></h2>
                <p><?php echo esc_html__('Recent activation and deactivation actions for modules.', 'gd-client-portal'); ?></p>
                <table class="widefat fixed" cellspacing="0" style="max-width:980px;margin-top:8px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Time', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Module', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Action', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Status', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Message', 'gd-client-portal'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $log = gd_client_portal_get_module_log(); ?>
                        <?php if (empty($log)) : ?>
                            <tr><td colspan="5"><?php echo esc_html__('No module log entries.', 'gd-client-portal'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach (array_reverse($log) as $entry) : ?>
                                <tr>
                                    <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                                    <td><?php echo esc_html($entry['slug'] ?? ''); ?></td>
                                    <td><?php echo esc_html($entry['action'] ?? ''); ?></td>
                                    <td><?php echo esc_html($entry['status'] ?? ''); ?></td>
                                    <td><?php echo esc_html($entry['message'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2><?php echo esc_html__('Modules', 'gd-client-portal'); ?></h2>
                <p><?php echo esc_html__('Enable or disable modules that should be autoloaded by the plugin.', 'gd-client-portal'); ?></p>
                <table class="widefat fixed" cellspacing="0" style="max-width:980px;margin-top:8px;">
                    <thead>
                        <tr>
                            <th style="width:60px;">&nbsp;</th>
                            <th><?php echo esc_html__('Module', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Description', 'gd-client-portal'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modules)) : ?>
                            <tr><td colspan="3"><?php echo esc_html__('No modules found.', 'gd-client-portal'); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ($modules as $mod) : ?>
                                <tr>
                                    <th scope="row">
                                        <label>
                                            <input type="checkbox" name="gd_client_portal_enabled_modules[]" value="<?php echo esc_attr($mod['slug']); ?>" <?php checked($mod['enabled']); ?> />
                                        </label>
                                    </th>
                                    <td><?php echo esc_html($mod['title']); ?></td>
                                    <td><?php echo esc_html($mod['description'] ?: __('No description available.', 'gd-client-portal')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'gd-client-portal')); ?>
            </form>
        </div>
        <?php
    }
}

add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/gd-client-portal.php'), 'gd_client_portal_add_settings_link');
add_action('admin_menu', 'gd_client_portal_register_admin_menu');
add_action('admin_init', 'gd_client_portal_register_settings');

// Marketplace admin: CRUD for items stored in an option
if (!function_exists('gd_client_portal_register_marketplace_menu')) {
    function gd_client_portal_register_marketplace_menu()
    {
        add_submenu_page(
            'gd-client-portal',
            __('Marketplace', 'gd-client-portal'),
            __('Marketplace', 'gd-client-portal'),
            'manage_options',
            'gd-client-portal-marketplace',
            'gd_client_portal_render_marketplace_admin_page'
        );

        add_submenu_page(
            'gd-client-portal',
            __('Marketplace Orders', 'gd-client-portal'),
            __('Marketplace Orders', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-marketplace-orders',
            'gd_client_portal_render_marketplace_orders_page'
        );
    }
    add_action('admin_menu', 'gd_client_portal_register_marketplace_menu', 20);
}

if (!function_exists('gd_client_portal_register_project_menus')) {
    function gd_client_portal_register_project_menus()
    {
        add_submenu_page(
            'gd-client-portal',
            __('Projects', 'gd-client-portal'),
            __('Projects', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-projects',
            'gd_client_portal_render_projects_admin_page'
        );

        add_submenu_page(
            'gd-client-portal',
            __('Project Messages', 'gd-client-portal'),
            __('Project Messages', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-project-messages',
            'gd_client_portal_render_project_messages_page'
        );

        add_submenu_page(
            'gd-client-portal',
            __('Deliverables', 'gd-client-portal'),
            __('Deliverables', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-deliverables',
            'gd_client_portal_render_deliverables_admin_page'
        );
    }
    add_action('admin_menu', 'gd_client_portal_register_project_menus', 20);
}

if (!function_exists('gd_client_portal_register_support_menu')) {
    function gd_client_portal_register_support_menu()
    {
        add_submenu_page(
            'gd-client-portal',
            __('Support Tickets', 'gd-client-portal'),
            __('Support Tickets', 'gd-client-portal'),
            'gd_client_portal_access_admin',
            'gd-client-portal-support-tickets',
            'gd_client_portal_render_support_tickets_page'
        );
    }
    add_action('admin_menu', 'gd_client_portal_register_support_menu', 20);
}

if (!function_exists('gd_client_portal_render_marketplace_orders_page')) {
    function gd_client_portal_render_marketplace_orders_page()
    {
        if (!gd_client_portal_user_can_access_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        // Tenant filtering: allow platform admins to filter, automatically scope tenant-admins
        $filter_tenant = null;
        if (!empty($_GET['filter_tenant'])) {
            $filter_tenant = absint($_GET['filter_tenant']);
        }

        if (gd_client_portal_user_is_tenant_admin() && !current_user_can('manage_options')) {
            // Tenant admins see only their tenant by default
            $filter_tenant = gd_client_portal_get_current_tenant_id();
        }

        $query_args = array(
            'post_type' => 'gd_client_portal_marketplace_order',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if (!is_null($filter_tenant) && $filter_tenant > 0) {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_gd_mp_order_tenant_id',
                    'value' => strval($filter_tenant),
                    'compare' => '=',
                ),
            );
        }

        $orders = get_posts($query_args);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Marketplace Orders', 'gd-client-portal'); ?></h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="gd-client-portal-marketplace-orders" />
                <label style="margin-right:8px;"><?php echo esc_html__('Filter by tenant:', 'gd-client-portal'); ?></label>
                <select name="filter_tenant">
                    <option value="0"><?php echo esc_html__('All / Unassigned', 'gd-client-portal'); ?></option>
                    <?php foreach (gd_client_portal_get_all_tenants() as $t) : $tid = absint($t['id'] ?? 0); if ($tid <= 0) continue; ?>
                        <option value="<?php echo esc_attr($tid); ?>" <?php selected(isset($_GET['filter_tenant']) ? intval($_GET['filter_tenant']) : '', $tid); ?>><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit"><?php echo esc_html__('Filter', 'gd-client-portal'); ?></button>
            </form>
            <?php if (empty($orders)) : ?>
                <p><?php echo esc_html__('No marketplace orders yet.', 'gd-client-portal'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Order', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Item', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Qty', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Total', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Reference', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Status', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Email', 'gd-client-portal'); ?></th>
                            <th><?php echo esc_html__('Date', 'gd-client-portal'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) : ?>
                            <?php
                            $item = maybe_unserialize(get_post_meta($order->ID, '_gd_mp_order_item', true));
                            $item_name = is_array($item) && !empty($item['title']) ? $item['title'] : $order->post_title;
                            $qty = absint(get_post_meta($order->ID, '_gd_mp_order_qty', true));
                            $total = get_post_meta($order->ID, '_gd_mp_order_total', true);
                            $reference = get_post_meta($order->ID, '_gd_mp_order_reference', true);
                            $email = get_post_meta($order->ID, '_gd_mp_order_email', true);
                            $status = get_post_meta($order->ID, '_gd_mp_order_status', true);
                            $status = $status ? $status : 'pending';
                            ?>
                            <tr>
                                <td>#<?php echo esc_html($order->ID); ?></td>
                                <td><?php echo esc_html($item_name); ?></td>
                                <td><?php echo esc_html($qty); ?></td>
                                <td><?php echo esc_html($total ? '$' . number_format(floatval($total), 2) : '—'); ?></td>
                                <td><?php echo esc_html($reference ?: '—'); ?></td>
                                <td><?php echo esc_html(ucfirst($status)); ?></td>
                                <td><?php echo esc_html($email ?: '—'); ?></td>
                                <td><?php echo esc_html(get_the_date('', $order)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_get_marketplace_items')) {
    function gd_client_portal_get_marketplace_items()
    {
        $items = get_option('gd_client_portal_marketplace_items', array());
        if (!is_array($items)) {
            $items = array();
        }
        return $items;
    }
}

if (!function_exists('gd_client_portal_get_marketplace_categories')) {
    function gd_client_portal_get_marketplace_categories()
    {
        $cats = get_option('gd_client_portal_marketplace_categories', array());
        if (!is_array($cats)) {
            $cats = array();
        }
        return $cats;
    }
}

if (!function_exists('gd_client_portal_handle_marketplace_save')) {
    function gd_client_portal_handle_marketplace_save()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add / update / bulk actions
        if (!empty($_POST['gd_client_portal_marketplace_action']) && in_array($_POST['gd_client_portal_marketplace_action'], array('add', 'update', 'delete', 'enable_all', 'seed', 'map_create_wc'), true)) {
            if (empty($_POST['gd_client_portal_marketplace_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_marketplace_nonce']), 'gd_client_portal_marketplace_save')) {
                wp_die(esc_html__('Invalid request.', 'gd-client-portal'));
            }

            $action = sanitize_text_field(wp_unslash($_POST['gd_client_portal_marketplace_action']));
            $items = gd_client_portal_get_marketplace_items();

            if ($action === 'add' || $action === 'update') {
                $title = isset($_POST['mp_title']) ? sanitize_text_field(wp_unslash($_POST['mp_title'])) : '';
                $desc = isset($_POST['mp_description']) ? sanitize_textarea_field(wp_unslash($_POST['mp_description'])) : '';
                $price = isset($_POST['mp_price']) ? floatval(wp_unslash($_POST['mp_price'])) : 0;
                $sku = isset($_POST['mp_sku']) ? sanitize_text_field(wp_unslash($_POST['mp_sku'])) : '';
                $enabled = !empty($_POST['mp_enabled']) ? 1 : 0;
                $image_id = isset($_POST['mp_image_id']) ? absint(wp_unslash($_POST['mp_image_id'])) : 0;
                $category = isset($_POST['mp_category']) ? sanitize_text_field(wp_unslash($_POST['mp_category'])) : '';
                $wc_product_id = isset($_POST['mp_wc_product_id']) ? absint(wp_unslash($_POST['mp_wc_product_id'])) : 0;
                $tenant_id = isset($_POST['mp_tenant_id']) ? absint(wp_unslash($_POST['mp_tenant_id'])) : gd_client_portal_get_current_tenant_id();

                if ($tenant_id <= 0) {
                    $tenant_id = absint(get_option('gd_client_portal_default_tenant_id', 0));
                }

                if ($action === 'add') {
                    $id = time() . rand(1000, 9999);
                } else {
                    $id = isset($_POST['mp_id']) ? sanitize_text_field(wp_unslash($_POST['mp_id'])) : '';
                }

                if (!$id) {
                    wp_die(esc_html__('Missing item id.', 'gd-client-portal'));
                }

                $items[$id] = array(
                    'id' => $id,
                    'title' => $title,
                    'description' => $desc,
                    'price' => $price,
                    'sku' => $sku,
                    'enabled' => $enabled,
                    'image_id' => $image_id,
                    'category' => $category,
                    'wc_product_id' => $wc_product_id,
                    'tenant_id' => $tenant_id,
                );

                update_option('gd_client_portal_marketplace_items', $items);
                // The plugin-native marketplace checkout uses Paystack directly.
                // Keep WooCommerce mapping as a legacy fallback only when Paystack is not enabled.
                $paystack_config = gd_client_portal_marketplace_get_paystack_config();
                if (empty($paystack_config['enabled']) && (function_exists('wc_get_product_id_by_sku') || function_exists('WC'))) {
                    foreach (array($items[$id]) as $tmp) {
                        // handled below
                    }
                    if (empty($items[$id]['wc_product_id']) && !empty($items[$id]['sku'])) {
                        if (function_exists('wc_get_product_id_by_sku')) {
                            $found = wc_get_product_id_by_sku($items[$id]['sku']);
                            if ($found) {
                                $items[$id]['wc_product_id'] = absint($found);
                                update_option('gd_client_portal_marketplace_items', $items);
                            }
                        }
                        if (empty($items[$id]['wc_product_id']) && function_exists('wp_insert_post')) {
                            $post = array(
                                'post_title' => $items[$id]['title'],
                                'post_content' => $items[$id]['description'],
                                'post_status' => 'publish',
                                'post_type' => 'product',
                            );
                            $post_id = wp_insert_post($post);
                            if ($post_id && !is_wp_error($post_id)) {
                                if (function_exists('wp_set_object_terms')) {
                                    wp_set_object_terms($post_id, 'simple', 'product_type');
                                }
                                if (!empty($items[$id]['sku'])) {
                                    update_post_meta($post_id, '_sku', sanitize_text_field($items[$id]['sku']));
                                }
                                $price_val = floatval($items[$id]['price']);
                                if (function_exists('wc_format_decimal')) {
                                    $price_val = wc_format_decimal($price_val);
                                }
                                update_post_meta($post_id, '_regular_price', $price_val);
                                update_post_meta($post_id, '_price', $price_val);
                                update_post_meta($post_id, '_stock_status', 'instock');
                                if (!empty($items[$id]['image_id']) && function_exists('set_post_thumbnail')) {
                                    set_post_thumbnail($post_id, absint($items[$id]['image_id']));
                                }
                                $items[$id]['wc_product_id'] = absint($post_id);
                                update_option('gd_client_portal_marketplace_items', $items);
                            }
                        }
                    }
                }
                wp_safe_redirect(remove_query_arg(array('action', 'mp_id'), add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace'))));
                exit;
            }

            if ($action === 'enable_all') {
                if (!empty($items)) {
                    foreach ($items as $k => $it) {
                        $items[$k]['enabled'] = 1;
                        if (empty($items[$k]['tenant_id'])) {
                            $items[$k]['tenant_id'] = absint(get_option('gd_client_portal_default_tenant_id', 0));
                        }
                    }
                    update_option('gd_client_portal_marketplace_items', $items);
                }
                wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace')));
                exit;
            }

            if ($action === 'seed') {
                // seed a few sample items if none exist
                if (empty($items)) {
                    $sample = array();
                    for ($i=1;$i<=4;$i++) {
                        $id = time() . rand(1000,9999) + $i;
                        $sample[$id] = array(
                            'id' => $id,
                            'title' => 'Sample Item ' . $i,
                            'description' => 'This is a sample marketplace item.',
                            'price' => (9.99 * $i),
                            'sku' => 'SAMPLE' . $i,
                            'enabled' => 1,
                            'image_id' => 0,
                            'category' => '',
                            'wc_product_id' => 0,
                            'tenant_id' => absint(get_option('gd_client_portal_default_tenant_id', 0)),
                        );
                    }
                    $items = $sample;
                    update_option('gd_client_portal_marketplace_items', $items);
                }
                wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace')));
                exit;
            }

            if ($action === 'map_create_wc') {
                $paystack_config = gd_client_portal_marketplace_get_paystack_config();
                if (empty($paystack_config['enabled']) && !empty($items)) {
                    foreach ($items as $k => $it) {
                        if (!empty($it['wc_product_id'])) { continue; }
                        $mapped = 0;
                        if (!empty($it['sku']) && function_exists('wc_get_product_id_by_sku')) {
                            $found = wc_get_product_id_by_sku($it['sku']);
                            if ($found) { $mapped = absint($found); }
                        }
                        if (empty($mapped) && function_exists('wp_insert_post')) {
                            $post = array(
                                'post_title' => $it['title'],
                                'post_content' => $it['description'],
                                'post_status' => 'publish',
                                'post_type' => 'product',
                            );
                            $post_id = wp_insert_post($post);
                            if ($post_id && !is_wp_error($post_id)) {
                                if (function_exists('wp_set_object_terms')) {
                                    wp_set_object_terms($post_id, 'simple', 'product_type');
                                }
                                if (!empty($it['sku'])) {
                                    update_post_meta($post_id, '_sku', sanitize_text_field($it['sku']));
                                }
                                $price_val = floatval($it['price']);
                                if (function_exists('wc_format_decimal')) { $price_val = wc_format_decimal($price_val); }
                                update_post_meta($post_id, '_regular_price', $price_val);
                                update_post_meta($post_id, '_price', $price_val);
                                update_post_meta($post_id, '_stock_status', 'instock');
                                if (!empty($it['image_id']) && function_exists('set_post_thumbnail')) {
                                    set_post_thumbnail($post_id, absint($it['image_id']));
                                }
                                $mapped = absint($post_id);
                            }
                        }
                        if ($mapped) {
                            $items[$k]['wc_product_id'] = $mapped;
                        }
                    }
                    update_option('gd_client_portal_marketplace_items', $items);
                }
                wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace')));
                exit;
            }

            if ($action === 'delete') {
                $id = isset($_POST['mp_id']) ? sanitize_text_field(wp_unslash($_POST['mp_id'])) : '';
                if ($id && isset($items[$id])) {
                    unset($items[$id]);
                    update_option('gd_client_portal_marketplace_items', $items);
                }
                wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace')));
                exit;
            }
        }
        // Category actions: add/delete
        if (!empty($_POST['gd_client_portal_marketplace_cat_action']) && in_array($_POST['gd_client_portal_marketplace_cat_action'], array('add_cat', 'delete_cat'), true)) {
            if (empty($_POST['gd_client_portal_marketplace_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_marketplace_nonce']), 'gd_client_portal_marketplace_save')) {
                wp_die(esc_html__('Invalid request.', 'gd-client-portal'));
            }

            $cats = gd_client_portal_get_marketplace_categories();
            $cat_action = sanitize_text_field(wp_unslash($_POST['gd_client_portal_marketplace_cat_action']));
            if ($cat_action === 'add_cat') {
                $name = isset($_POST['mp_category_name']) ? sanitize_text_field(wp_unslash($_POST['mp_category_name'])) : '';
                if ($name) {
                    $id = time() . rand(100, 999);
                    $cats[$id] = $name;
                    update_option('gd_client_portal_marketplace_categories', $cats);
                }
            } elseif ($cat_action === 'delete_cat') {
                $cat_id = isset($_POST['mp_cat_id']) ? sanitize_text_field(wp_unslash($_POST['mp_cat_id'])) : '';
                if ($cat_id && isset($cats[$cat_id])) {
                    unset($cats[$cat_id]);
                    update_option('gd_client_portal_marketplace_categories', $cats);
                }
            }

            wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('admin.php?page=gd-client-portal-marketplace')));
            exit;
        }
    }
}

// Enqueue media uploader for marketplace admin
if (!function_exists('gd_client_portal_enqueue_marketplace_admin_scripts')) {
    function gd_client_portal_enqueue_marketplace_admin_scripts($hook)
    {
        // only enqueue on our marketplace admin page
        if (isset($_GET['page']) && $_GET['page'] === 'gd-client-portal-marketplace') {
            wp_enqueue_media();
            $js = <<<'JS'
(function($){
    $(function(){
        var fileFrame;
        $('body').on('click', '.gd-mp-upload', function(e){
            e.preventDefault();
            var button = $(this);
            if (fileFrame) { fileFrame.open(); return; }
            fileFrame = wp.media({ title: 'Select or Upload Image', library: { type: 'image' }, multiple: false });
            fileFrame.on('select', function(){
                var attachment = fileFrame.state().get('selection').first().toJSON();
                button.siblings('input.mp-image-id').val(attachment.id);
                button.siblings('.mp-thumb').html('<img src="'+attachment.url+'" class="mp-thumb-img"/>');
            });
            fileFrame.open();
        });
        $('body').on('click', '.gd-mp-remove', function(e){ e.preventDefault(); $(this).siblings('input.mp-image-id').val(''); $(this).siblings('.mp-thumb').html(''); });
    });
})(jQuery);
JS;
            wp_add_inline_script('jquery', $js);
        }
    }
    add_action('admin_enqueue_scripts', 'gd_client_portal_enqueue_marketplace_admin_scripts');
}

if (!function_exists('gd_client_portal_render_marketplace_admin_page')) {
    function gd_client_portal_render_marketplace_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        // Handle form submissions
        gd_client_portal_handle_marketplace_save();

        $items = gd_client_portal_get_marketplace_items();

        $editing = false;
        $edit_item = null;
        if (!empty($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['mp_id'])) {
            $mp_id = sanitize_text_field(wp_unslash($_GET['mp_id']));
            if (isset($items[$mp_id])) {
                $editing = true;
                $edit_item = $items[$mp_id];
            }
        }

        ?>
        <div class="wrap">
            <style>
            /* Admin marketplace thumbnail: square, cover */
            .mp-thumb img{width:120px;height:120px;object-fit:cover;display:block;border-radius:6px}
            table.widefat img{width:80px;height:80px;object-fit:cover;border-radius:6px}
            </style>
            <h1><?php echo esc_html__('Marketplace', 'gd-client-portal'); ?></h1>
            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="updated notice"><p><?php echo esc_html__('Marketplace updated.', 'gd-client-portal'); ?></p></div>
            <?php endif; ?>

            <h2><?php echo esc_html__('Categories', 'gd-client-portal'); ?></h2>
            <?php $cats = gd_client_portal_get_marketplace_categories(); ?>
            <table class="widefat fixed" cellspacing="0" style="max-width:980px;margin-top:8px;">
                <thead><tr><th><?php echo esc_html__('Category', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Actions', 'gd-client-portal'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($cats)) : ?>
                        <tr><td colspan="2"><?php echo esc_html__('No categories configured.', 'gd-client-portal'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($cats as $cid => $cname) : ?>
                            <tr>
                                <td><?php echo esc_html($cname); ?></td>
                                <td>
                                    <form style="display:inline-block;" method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>">
                                        <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                                        <input type="hidden" name="gd_client_portal_marketplace_cat_action" value="delete_cat" />
                                        <input type="hidden" name="mp_cat_id" value="<?php echo esc_attr($cid); ?>" />
                                        <button class="button" type="submit" onclick="return confirm('<?php echo esc_js(__('Delete this category?','gd-client-portal')); ?>')"><?php echo esc_html__('Delete', 'gd-client-portal'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>" style="max-width:480px;margin-top:12px;">
                <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                <input type="hidden" name="gd_client_portal_marketplace_cat_action" value="add_cat" />
                <label style="display:inline-block;margin-right:8px;"><input type="text" name="mp_category_name" placeholder="New category" /></label>
                <?php submit_button(__('Add category','gd-client-portal'), 'secondary', '', false); ?>
            </form>

            <h2 style="margin-top:20px"><?php echo esc_html__('Items', 'gd-client-portal'); ?></h2>
            <div style="margin-top:8px;margin-bottom:8px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>" style="display:inline-block;margin-right:8px;">
                    <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                    <input type="hidden" name="gd_client_portal_marketplace_action" value="enable_all" />
                    <?php submit_button(__('Enable all items','gd-client-portal'), 'secondary', '', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>" style="display:inline-block;">
                    <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                    <input type="hidden" name="gd_client_portal_marketplace_action" value="seed" />
                    <?php submit_button(__('Seed sample items','gd-client-portal'), 'secondary', '', false); ?>
                </form>
            </div>
            <?php $all_tenants = gd_client_portal_get_all_tenants(); ?>
            <table class="widefat fixed" cellspacing="0" style="max-width:980px;margin-top:8px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Thumb', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Title', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('SKU', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Price', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Category', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Enabled', 'gd-client-portal'); ?></th>
                        <th><?php echo esc_html__('Actions', 'gd-client-portal'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr><td colspan="8"><?php echo esc_html__('No items configured.', 'gd-client-portal'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($items as $it) : ?>
                            <?php $tenant_label = __('Unassigned', 'gd-client-portal'); $tenant_id_value = absint($it['tenant_id'] ?? 0); if ($tenant_id_value > 0 && isset($all_tenants[$tenant_id_value])) { $tenant_label = $all_tenants[$tenant_id_value]['name']; } ?>
                            <tr>
                                <td style="width:120px;">
                                    <?php if (!empty($it['image_id'])) {
                                        echo wp_get_attachment_image($it['image_id'], array(80,80));
                                    } else { echo '&nbsp;'; } ?>
                                </td>
                                <td><?php echo esc_html($it['title']); ?></td>
                                <td><?php echo esc_html($it['sku']); ?></td>
                                <td><?php echo esc_html(number_format_i18n($it['price'], 2)); ?></td>
                                <td><?php echo esc_html($it['category'] ? (isset($cats[$it['category']]) ? $cats[$it['category']] : $it['category']) : ''); ?></td>
                                <td><?php echo esc_html($tenant_label); ?></td>
                                <td><?php echo $it['enabled'] ? esc_html__('Yes', 'gd-client-portal') : esc_html__('No', 'gd-client-portal'); ?></td>
                                <td>
                                    <a class="button" href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'mp_id' => $it['id']))); ?>"><?php echo esc_html__('Edit', 'gd-client-portal'); ?></a>
                                    <form style="display:inline-block;margin-left:6px;" method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>">
                                        <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                                        <input type="hidden" name="gd_client_portal_marketplace_action" value="delete" />
                                        <input type="hidden" name="mp_id" value="<?php echo esc_attr($it['id']); ?>" />
                                        <button class="button" type="submit" onclick="return confirm('<?php echo esc_js(__('Delete this item?','gd-client-portal')); ?>')"><?php echo esc_html__('Delete', 'gd-client-portal'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:10px;">
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>" style="display:inline-block;margin-right:8px;">
                    <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                    <input type="hidden" name="gd_client_portal_marketplace_action" value="map_create_wc" />
                    <?php submit_button(__('Map/Create WC products','gd-client-portal'), 'secondary', '', false); ?>
                </form>
                <span class="description"><?php echo esc_html__('Attempt to map by SKU or create simple WC products for items without a mapped product.', 'gd-client-portal'); ?></span>
            </div>

            <h2 style="margin-top:30px;"><?php echo $editing ? esc_html__('Edit Item', 'gd-client-portal') : esc_html__('Add New Item', 'gd-client-portal'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-marketplace')); ?>" style="max-width:720px;">
                <?php wp_nonce_field('gd_client_portal_marketplace_save', 'gd_client_portal_marketplace_nonce'); ?>
                <input type="hidden" name="gd_client_portal_marketplace_action" value="<?php echo $editing ? 'update' : 'add'; ?>" />
                <?php if ($editing) : ?>
                    <input type="hidden" name="mp_id" value="<?php echo esc_attr($edit_item['id']); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mp_title"><?php echo esc_html__('Title', 'gd-client-portal'); ?></label></th>
                        <td><input name="mp_title" type="text" id="mp_title" value="<?php echo esc_attr($editing ? $edit_item['title'] : ''); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mp_image"><?php echo esc_html__('Image', 'gd-client-portal'); ?></label></th>
                        <td>
                            <div class="mp-thumb" style="display:inline-block;vertical-align:middle;margin-right:8px;">
                                <?php if ($editing && !empty($edit_item['image_id'])) { echo wp_get_attachment_image($edit_item['image_id'], array(120,120)); } ?>
                            </div>
                            <input type="hidden" name="mp_image_id" class="mp-image-id" value="<?php echo esc_attr($editing ? $edit_item['image_id'] : ''); ?>" />
                            <button class="button gd-mp-upload"><?php echo esc_html__('Upload/Select', 'gd-client-portal'); ?></button>
                            <button class="button gd-mp-remove" style="margin-left:6px"><?php echo esc_html__('Remove', 'gd-client-portal'); ?></button>
                        </td>
                    </tr>
                        <tr>
                            <th scope="row"><label for="mp_sku"><?php echo esc_html__('SKU', 'gd-client-portal'); ?></label></th>
                            <td><input name="mp_sku" type="text" id="mp_sku" value="<?php echo esc_attr($editing ? $edit_item['sku'] : ''); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mp_wc_product_id"><?php echo esc_html__('WooCommerce product ID (legacy)', 'gd-client-portal'); ?></label></th>
                            <td><input name="mp_wc_product_id" type="number" id="mp_wc_product_id" value="<?php echo esc_attr($editing ? ($edit_item['wc_product_id'] ?? '') : ''); ?>" class="regular-text" /><p class="description"><?php echo esc_html__('Legacy only. Paystack checkout does not require a WooCommerce product mapping.', 'gd-client-portal'); ?></p></td>
                        </tr>
                    <tr>
                        <th scope="row"><label for="mp_price"><?php echo esc_html__('Price', 'gd-client-portal'); ?></label></th>
                        <td><input name="mp_price" type="number" step="0.01" id="mp_price" value="<?php echo esc_attr($editing ? $edit_item['price'] : '0.00'); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mp_description"><?php echo esc_html__('Description', 'gd-client-portal'); ?></label></th>
                        <td><textarea name="mp_description" id="mp_description" rows="6" class="large-text"><?php echo esc_textarea($editing ? $edit_item['description'] : ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mp_category_select"><?php echo esc_html__('Category', 'gd-client-portal'); ?></label></th>
                        <td>
                            <?php $cats = gd_client_portal_get_marketplace_categories(); ?>
                            <select id="mp_category_select" name="mp_category">
                                <option value=""><?php echo esc_html__('— None —', 'gd-client-portal'); ?></option>
                                <?php foreach ($cats as $cid => $cname) : ?>
                                    <option value="<?php echo esc_attr($cid); ?>" <?php selected($editing ? $edit_item['category'] : '', $cid); ?>><?php echo esc_html($cname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mp_tenant_id"><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></label></th>
                        <td>
                            <?php $default_item_tenant = $editing ? absint($edit_item['tenant_id'] ?? 0) : gd_client_portal_get_current_tenant_id(); ?>
                            <select id="mp_tenant_id" name="mp_tenant_id">
                                <option value="0"><?php echo esc_html__('Unassigned', 'gd-client-portal'); ?></option>
                                <?php foreach (gd_client_portal_get_all_tenants() as $tenant) : ?>
                                    <?php $tenant_id_value = absint($tenant['id'] ?? 0); ?>
                                    <?php if ($tenant_id_value <= 0) continue; ?>
                                    <option value="<?php echo esc_attr($tenant_id_value); ?>" <?php selected($default_item_tenant, $tenant_id_value); ?>><?php echo esc_html($tenant['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enabled', 'gd-client-portal'); ?></th>
                        <td><label><input name="mp_enabled" type="checkbox" value="1" <?php checked($editing && !empty($edit_item['enabled'])); ?> /> <?php echo esc_html__('Make item visible in the marketplace', 'gd-client-portal'); ?></label></td>
                    </tr>
                </table>

                <?php submit_button($editing ? __('Update Item', 'gd-client-portal') : __('Add Item', 'gd-client-portal')); ?>
            </form>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_support_tickets_page')) {
    function gd_client_portal_render_support_tickets_page()
    {
        if (!gd_client_portal_user_can_access_admin()) { wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal')); }

        // Try to load support tickets stored as posts (support_ticket or gd_support_ticket)
        $post_types = array('gd_support_ticket', 'support_ticket', 'ticket');
        $found_posts = array();
        foreach ($post_types as $pt) {
            if (post_type_exists($pt)) {
                $found_posts = get_posts(array('post_type' => $pt, 'posts_per_page' => -1, 'post_status' => 'any'));
                break;
            }
        }

        // Tenant filter
        $filter_tenant = null;
        if (!empty($_GET['filter_tenant'])) { $filter_tenant = absint($_GET['filter_tenant']); }
        if (gd_client_portal_user_is_tenant_admin() && !current_user_can('manage_options')) { $filter_tenant = gd_client_portal_get_current_tenant_id(); }

        $tenants = gd_client_portal_get_all_tenants();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Support Tickets', 'gd-client-portal'); ?></h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="gd-client-portal-support-tickets" />
                <label style="margin-right:8px;"><?php echo esc_html__('Filter by tenant:', 'gd-client-portal'); ?></label>
                <select name="filter_tenant">
                    <option value="0"><?php echo esc_html__('All / Unassigned', 'gd-client-portal'); ?></option>
                    <?php foreach ($tenants as $t) : $tid = absint($t['id'] ?? 0); if ($tid <= 0) continue; ?>
                        <option value="<?php echo esc_attr($tid); ?>" <?php selected(isset($_GET['filter_tenant']) ? intval($_GET['filter_tenant']) : '', $tid); ?>><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit"><?php echo esc_html__('Filter', 'gd-client-portal'); ?></button>
            </form>

            <?php if (empty($found_posts)) : ?>
                <p><?php echo esc_html__('No support tickets found (no post type registered).', 'gd-client-portal'); ?></p>
            <?php else : ?>
                <table class="widefat striped" style="margin-top:12px;">
                    <thead><tr><th><?php echo esc_html__('ID', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Title', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Author', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Status', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Date', 'gd-client-portal'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($found_posts as $p) :
                            $post_tenant = absint(get_post_meta($p->ID, '_gd_support_tenant_id', true));
                            if (!empty($filter_tenant) && $filter_tenant > 0 && $post_tenant !== $filter_tenant) { continue; }
                            $tenant_label = $post_tenant > 0 && isset($tenants[$post_tenant]) ? $tenants[$post_tenant]['name'] : __('Unassigned','gd-client-portal');
                            $author = gd_client_portal_cached_user($p->post_author);
                        ?>
                            <tr>
                                <td>#<?php echo esc_html($p->ID); ?></td>
                                <td><?php echo esc_html($p->post_title); ?></td>
                                <td><?php echo $author ? esc_html($author->display_name) : esc_html__('Guest','gd-client-portal'); ?></td>
                                <td><?php echo esc_html($tenant_label); ?></td>
                                <td><?php echo esc_html($p->post_status); ?></td>
                                <td><?php echo esc_html(get_the_date('', $p)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_projects_admin_page')) {
    function gd_client_portal_render_projects_admin_page()
    {
        if (!gd_client_portal_user_can_access_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'gd_projects';

        $filter_tenant = null;
        if (!empty($_GET['filter_tenant'])) {
            $filter_tenant = absint($_GET['filter_tenant']);
        }
        if (gd_client_portal_user_is_tenant_admin() && !current_user_can('manage_options')) {
            $filter_tenant = gd_client_portal_get_current_tenant_id();
        }

        $sql = "SELECT * FROM $table";
        $where = array();
        $params = array();
        if (!is_null($filter_tenant) && $filter_tenant > 0) {
            $where[] = "tenant_id = %d";
            $params[] = $filter_tenant;
        }
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= ' ORDER BY id DESC';
        $prepared = $wpdb->prepare($sql, $params);
        $projects = $wpdb->get_results($prepared);

        $tenants = gd_client_portal_get_all_tenants();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Projects', 'gd-client-portal'); ?></h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="gd-client-portal-projects" />
                <label style="margin-right:8px;"><?php echo esc_html__('Filter by tenant:', 'gd-client-portal'); ?></label>
                <select name="filter_tenant">
                    <option value="0"><?php echo esc_html__('All / Unassigned', 'gd-client-portal'); ?></option>
                    <?php foreach ($tenants as $t) : $tid = absint($t['id'] ?? 0); if ($tid <= 0) continue; ?>
                        <option value="<?php echo esc_attr($tid); ?>" <?php selected(isset($_GET['filter_tenant']) ? intval($_GET['filter_tenant']) : '', $tid); ?>><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit"><?php echo esc_html__('Filter', 'gd-client-portal'); ?></button>
            </form>

            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th><?php echo esc_html__('Project ID', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Title', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Owner', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Status', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Created', 'gd-client-portal'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($projects)) : ?>
                        <tr><td colspan="6"><?php echo esc_html__('No projects found.', 'gd-client-portal'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($projects as $p) : $tenant_label = __('Unassigned', 'gd-client-portal'); $tid = absint($p->tenant_id ?? 0); if ($tid > 0 && isset($tenants[$tid])) { $tenant_label = $tenants[$tid]['name']; } $owner = gd_client_portal_cached_user($p->user_id); ?>
                            <tr>
                                <td>#<?php echo esc_html(intval($p->id)); ?></td>
                                <td><?php echo esc_html($p->title); ?></td>
                                <td><?php echo $owner ? esc_html($owner->display_name) : esc_html__('—', 'gd-client-portal'); ?></td>
                                <td><?php echo esc_html($tenant_label); ?></td>
                                <td><?php echo esc_html($p->status); ?></td>
                                <td><?php echo esc_html($p->created_at ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_project_messages_page')) {
    function gd_client_portal_render_project_messages_page()
    {
        if (!gd_client_portal_user_can_access_admin()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
        }

        $filter_tenant = null;
        if (!empty($_GET['filter_tenant'])) { $filter_tenant = absint($_GET['filter_tenant']); }
        if (gd_client_portal_user_is_tenant_admin() && !current_user_can('manage_options')) { $filter_tenant = gd_client_portal_get_current_tenant_id(); }
        $messages = gdcp_message_service()->list_for_tenant(($filter_tenant && $filter_tenant > 0) ? $filter_tenant : 0, 300);

        $tenants = gd_client_portal_get_all_tenants();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Project Messages', 'gd-client-portal'); ?></h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="gd-client-portal-project-messages" />
                <label style="margin-right:8px;"><?php echo esc_html__('Filter by tenant:', 'gd-client-portal'); ?></label>
                <select name="filter_tenant">
                    <option value="0"><?php echo esc_html__('All / Unassigned', 'gd-client-portal'); ?></option>
                    <?php foreach ($tenants as $t) : $tid = absint($t['id'] ?? 0); if ($tid <= 0) continue; ?>
                        <option value="<?php echo esc_attr($tid); ?>" <?php selected(isset($_GET['filter_tenant']) ? intval($_GET['filter_tenant']) : '', $tid); ?>><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit"><?php echo esc_html__('Filter', 'gd-client-portal'); ?></button>
            </form>

            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th><?php echo esc_html__('ID', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Project', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Author', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Message', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Created', 'gd-client-portal'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($messages)) : ?>
                        <tr><td colspan="6"><?php echo esc_html__('No messages found.', 'gd-client-portal'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($messages as $m) : $tid = absint($m->tenant_id ?? 0); $tenant_label = ($tid > 0 && isset($tenants[$tid])) ? $tenants[$tid]['name'] : __('Unassigned','gd-client-portal'); $author = gd_client_portal_cached_user($m->user_id); ?>
                            <tr>
                                <td><?php echo esc_html(intval($m->id)); ?></td>
                                <td><?php echo esc_html($m->project_title ?? '—'); ?></td>
                                <td><?php echo $author ? esc_html($author->display_name) : esc_html__('Guest','gd-client-portal'); ?></td>
                                <td><?php echo esc_html(mb_strimwidth($m->message, 0, 120, '...')); ?></td>
                                <td><?php echo esc_html($tenant_label); ?></td>
                                <td><?php echo esc_html($m->created_at ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

if (!function_exists('gd_client_portal_render_deliverables_admin_page')) {
    function gd_client_portal_render_deliverables_admin_page()
    {
        if (!gd_client_portal_user_can_access_admin()) { wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal')); }
        global $wpdb;
        $table = $wpdb->prefix . 'gd_projects';

        $filter_tenant = null; if (!empty($_GET['filter_tenant'])) { $filter_tenant = absint($_GET['filter_tenant']); }
        if (gd_client_portal_user_is_tenant_admin() && !current_user_can('manage_options')) { $filter_tenant = gd_client_portal_get_current_tenant_id(); }

        $sql = "SELECT * FROM $table WHERE (file_url IS NOT NULL AND file_url != '')";
        $params = array();
        if (!is_null($filter_tenant) && $filter_tenant > 0) { $sql .= ' AND tenant_id = %d'; $params[] = $filter_tenant; }
        $sql .= ' ORDER BY id DESC';
        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared);

        $tenants = gd_client_portal_get_all_tenants();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Deliverables', 'gd-client-portal'); ?></h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="gd-client-portal-deliverables" />
                <label style="margin-right:8px;"><?php echo esc_html__('Filter by tenant:', 'gd-client-portal'); ?></label>
                <select name="filter_tenant">
                    <option value="0"><?php echo esc_html__('All / Unassigned', 'gd-client-portal'); ?></option>
                    <?php foreach ($tenants as $t) : $tid = absint($t['id'] ?? 0); if ($tid <= 0) continue; ?>
                        <option value="<?php echo esc_attr($tid); ?>" <?php selected(isset($_GET['filter_tenant']) ? intval($_GET['filter_tenant']) : '', $tid); ?>><?php echo esc_html($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit"><?php echo esc_html__('Filter', 'gd-client-portal'); ?></button>
            </form>

            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th><?php echo esc_html__('ID', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Project', 'gd-client-portal'); ?></th><th><?php echo esc_html__('File', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Tenant', 'gd-client-portal'); ?></th><th><?php echo esc_html__('Created', 'gd-client-portal'); ?></th></tr></thead>
                <tbody>
                    <?php if (empty($rows)) : ?>
                        <tr><td colspan="5"><?php echo esc_html__('No deliverables found.', 'gd-client-portal'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($rows as $r) : $tid = absint($r->tenant_id ?? 0); $tenant_label = ($tid > 0 && isset($tenants[$tid])) ? $tenants[$tid]['name'] : __('Unassigned','gd-client-portal'); ?>
                            <tr>
                                <td><?php echo esc_html(intval($r->id)); ?></td>
                                <td><?php echo esc_html($r->title); ?></td>
                                <td><?php echo $r->file_url ? '<a href="' . esc_url($r->file_url) . '" target="_blank">' . esc_html(basename($r->file_url)) . '</a>' : esc_html__('—','gd-client-portal'); ?></td>
                                <td><?php echo esc_html($tenant_label); ?></td>
                                <td><?php echo esc_html($r->created_at ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}