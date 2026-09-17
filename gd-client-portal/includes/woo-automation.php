<?php
/**
 * WooCommerce Automation 2.0.
 * Purchase -> tenant/client -> project -> requirements -> workflow -> approval -> delivery.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('gd_client_portal_woo_automation_enabled')) {
    function gd_client_portal_woo_automation_enabled() {
        return get_option('gd_client_portal_woo_automation_enabled', '1') === '1';
    }
}

if (!function_exists('gd_client_portal_woo_automation_find_user_by_order')) {
    function gd_client_portal_woo_automation_find_user_by_order($order) {
        if (!is_a($order, 'WC_Order')) return 0;
        $user_id = absint($order->get_user_id());
        if ($user_id > 0) return $user_id;
        $email = sanitize_email($order->get_billing_email());
        if (!$email) return 0;
        $user = get_user_by('email', $email);
        return $user ? absint($user->ID) : 0;
    }
}

if (!function_exists('gd_client_portal_woo_automation_resolve_tenant')) {
    function gd_client_portal_woo_automation_resolve_tenant($order, $user_id = 0) {
        $tenant_id = is_a($order, 'WC_Order') ? absint($order->get_meta('_gd_mp_order_tenant_id')) : 0;
        if ($tenant_id <= 0 && $user_id > 0) {
            $tenant_id = gd_client_portal_get_user_tenant_id($user_id);
        }
        if ($tenant_id <= 0) {
            $tenant_id = gd_client_portal_get_default_tenant_id();
        }
        return $tenant_id;
    }
}

if (!function_exists('gd_client_portal_woo_automation_mark_order')) {
    function gd_client_portal_woo_automation_mark_order($order, $status) {
        if (!is_a($order, 'WC_Order')) return;
        $order->update_meta_data('_gd_client_portal_automation_status', sanitize_key($status));
        $order->update_meta_data('_gd_client_portal_automation_at', current_time('mysql'));
        $order->save();
    }
}

if (!function_exists('gd_client_portal_woo_automation_notify_project_created')) {
    function gd_client_portal_woo_automation_notify_project_created($project_id) {
        $project = gd_client_portal_cached_project($project_id);
        if (!$project) return;
        do_action('gd_client_portal_project_created', $project);
    }
}

if (!function_exists('gd_client_portal_woo_automation_create_projects')) {
    function gd_client_portal_woo_automation_create_projects($order_id) {
        global $wpdb;
        if (!gd_client_portal_woo_automation_enabled() || !gd_client_portal_is_woocommerce_active()) return array();
        $order = wc_get_order($order_id);
        if (!$order || !gd_client_portal_order_contains_service($order)) return array();

        // Only paid/processing/completed orders enter the delivery workflow.
        $status = $order->get_status();
        if (!in_array($status, array('processing', 'completed'), true) && !$order->is_paid()) return array();

        $user_id = gd_client_portal_woo_automation_find_user_by_order($order);
        $tenant_id = gd_client_portal_woo_automation_resolve_tenant($order, $user_id);
        $table = gd_client_portal_get_project_table_name();
        $created_ids = array();

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = absint($item->get_product_id());
            if ($product_id <= 0 || !has_term('service', 'product_cat', $product_id)) continue;

            $existing_item_project = absint($item->get_meta('_gd_client_portal_project_id', true));
            if ($existing_item_project) {
                $created_ids[] = $existing_item_project;
                continue;
            }

            // Backward-compatible duplicate guard for projects created by v1.x.
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id=%d AND product_id=%d LIMIT 1", $order_id, $product_id));
            if ($existing) {
                $item->update_meta_data('_gd_client_portal_project_id', absint($existing));
                $item->save();
                $created_ids[] = absint($existing);
                continue;
            }

            $service_type = gd_client_portal_get_service_type($product_id);
            $workflow = gd_client_portal_get_workflow($service_type);
            $first_stage = !empty($workflow) ? $workflow[0] : 'intake';
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : $item->get_name();
            $title = $product_name ? $product_name . ' — ' . sprintf(__('Order #%d', 'gd-client-portal'), $order_id) : sprintf(__('Service Project #%d', 'gd-client-portal'), $order_id);
            $description = sprintf(__('New service purchase created from WooCommerce order #%d. Requirements are now awaiting submission.', 'gd-client-portal'), $order_id);

            $lock_name = 'gdcp_woo_project_' . absint($order_id) . '_' . absint($product_id);
            $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $lock_name));
            if ((string)$locked !== '1') continue;
            try {
                // Re-check after acquiring the database lock: another PHP worker
                // may have created and linked the project between the first read
                // and this point.
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE order_id=%d AND product_id=%d LIMIT 1", $order_id, $product_id));
                if ($existing) {
                    $item->update_meta_data('_gd_client_portal_project_id', absint($existing));
                    $item->update_meta_data('_gd_client_portal_tenant_id', $tenant_id);
                    $item->save();
                    $created_ids[] = absint($existing);
                    continue;
                }
                $project_service = function_exists('gdcp_service') ? gdcp_service('project') : null;
                if (!$project_service || !method_exists($project_service,'create_from_woocommerce')) continue;
                $project_id = absint($project_service->create_from_woocommerce(array(
                    'tenant_id'=>$tenant_id,'order_id'=>$order_id,'user_id'=>$user_id,'product_id'=>$product_id,
                    'service_type'=>$service_type,'title'=>$title,'description'=>$description,
                    'status'=>'awaiting_requirements','current_stage'=>$first_stage,'progress'=>0,
                )));
                if (!$project_id) continue;
            } finally {
                $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            }
            $item->update_meta_data('_gd_client_portal_project_id', $project_id);
            $item->update_meta_data('_gd_client_portal_tenant_id', $tenant_id);
            $item->save();
            $created_ids[] = $project_id;
            gd_client_portal_woo_automation_notify_project_created($project_id);
        }

        if ($created_ids) {
            $order->update_meta_data('_gd_client_portal_project_ids', implode(',', array_unique(array_map('absint', $created_ids))));
            gd_client_portal_woo_automation_mark_order($order, 'project_created');
        }
        return array_values(array_unique(array_map('absint', $created_ids)));
    }
}

if (!function_exists('gd_client_portal_woo_automation_claim_guest_projects')) {
    function gd_client_portal_woo_automation_claim_guest_projects($user_id) {
        global $wpdb;
        $user_id = absint($user_id);
        if (!$user_id || !gd_client_portal_woo_automation_enabled()) return;
        $user = gd_client_portal_cached_user($user_id);
        if (!$user || !$user->user_email) return;
        $tenant_id = gd_client_portal_get_user_tenant_id($user_id);
        if ($tenant_id <= 0) $tenant_id = gd_client_portal_assign_default_tenant($user_id);
        if ($tenant_id <= 0) return;

        $table = gd_client_portal_get_project_table_name();
        $claim_key = 'gdcp_woo_guest_claim_' . md5(strtolower(sanitize_email($user->user_email)));
        $locked = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)', $claim_key));
        if ((string)$locked !== '1') return;
        try {
            // Re-read only after acquiring the per-email lock. Registration hooks can
            // fire concurrently (or be retried), so the claim operation itself must
            // be serialized and the final UPDATE must remain conditional on user_id=0.
            $projects = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=0 ORDER BY id DESC LIMIT 100"));
            if (!$projects) return;
            foreach ($projects as $project) {
                $order = wc_get_order($project->order_id);
                if (!$order || strtolower(sanitize_email($order->get_billing_email())) !== strtolower(sanitize_email($user->user_email))) continue;
                // Never move a project across tenants. Guest projects are claimable
                // only when unscoped or already assigned to this user's tenant.
                if (absint($project->tenant_id) > 0 && absint($project->tenant_id) !== $tenant_id) continue;
                $updated = $wpdb->query($wpdb->prepare("UPDATE $table SET user_id=%d, tenant_id=%d WHERE id=%d AND user_id=0 AND (tenant_id=0 OR tenant_id=%d)", $user_id, $tenant_id, absint($project->id), $tenant_id));
                if ($updated === 1) do_action('gd_client_portal_project_claimed', absint($project->id), $user_id);
            }
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $claim_key));
        }
    }
}

if (!function_exists('gd_client_portal_woo_automation_on_paid')) {
    function gd_client_portal_woo_automation_on_paid($order_id) {
        gd_client_portal_woo_automation_create_projects($order_id);
    }
    add_action('woocommerce_payment_complete', 'gd_client_portal_woo_automation_on_paid', 20);
    add_action('woocommerce_order_status_processing', 'gd_client_portal_woo_automation_on_paid', 20);
    add_action('woocommerce_order_status_completed', 'gd_client_portal_woo_automation_on_paid', 20);
}

if (!function_exists('gd_client_portal_woo_automation_on_registration')) {
    function gd_client_portal_woo_automation_on_registration($user_id) {
        gd_client_portal_woo_automation_claim_guest_projects($user_id);
    }
    add_action('user_register', 'gd_client_portal_woo_automation_on_registration', 40, 1);
}

// When a project is claimed/created, keep its order metadata synchronized.
add_action('gd_client_portal_project_claimed', function($project_id, $user_id) {
    $project = gd_client_portal_cached_project($project_id);
    if (!$project || !gd_client_portal_is_woocommerce_active()) return;
    $order = wc_get_order($project->order_id);
    if (!$order) return;
    $order->update_meta_data('_gd_client_portal_last_project_user', absint($user_id));
    $order->save();
}, 10, 2);
