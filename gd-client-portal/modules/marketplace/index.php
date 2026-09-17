<?php
/**
 * Marketplace module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_module_marketplace_activate')) {
	function gd_client_portal_module_marketplace_activate()
	{
		update_option('gd_client_portal_module_marketplace_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_marketplace_deactivate')) {
	function gd_client_portal_module_marketplace_deactivate()
	{
		update_option('gd_client_portal_module_marketplace_deactivated', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_register_marketplace_module')) {
	function gd_client_portal_register_marketplace_module()
	{
		add_shortcode('gd_client_portal_marketplace', 'gd_client_portal_render_marketplace');
		add_shortcode('gd_marketplace', 'gd_client_portal_render_marketplace');
		add_shortcode('gd_client_portal_marketplace_orders', 'gd_client_portal_marketplace_render_order_history');
		add_shortcode('gd_marketplace_orders', 'gd_client_portal_marketplace_render_order_history');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_marketplace_assets');
	}
}

if (!function_exists('gd_client_portal_register_marketplace_assets')) {
	function gd_client_portal_register_marketplace_assets()
	{
		wp_register_style('gd-client-portal-marketplace', GD_CLIENT_PORTAL_URL . 'modules/marketplace/assets/marketplace.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
			wp_register_script('gd-client-portal-marketplace', GD_CLIENT_PORTAL_URL . 'modules/marketplace/assets/marketplace.js', array('jquery'), GD_CLIENT_PORTAL_VERSION, true);
			wp_register_script('gd-client-portal-marketplace-native', GD_CLIENT_PORTAL_URL . 'modules/marketplace/assets/native-bridge.js', array('gd-client-portal-marketplace'), GD_CLIENT_PORTAL_VERSION, true);
	}
}

if (!function_exists('gd_client_portal_marketplace_register_order_type')) {
	function gd_client_portal_marketplace_register_order_type()
	{
		register_post_type('gd_client_portal_marketplace_order', array(
			'labels' => array(
				'name' => __('Marketplace Orders', 'gd-client-portal'),
				'singular_name' => __('Marketplace Order', 'gd-client-portal'),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => 'gd-client-portal',
			'supports' => array('title', 'custom-fields'),
			'capability_type' => 'post',
			'menu_icon' => 'dashicons-cart',
		));
	}
	add_action('init', 'gd_client_portal_marketplace_register_order_type');
}

if (!function_exists('gd_client_portal_marketplace_get_paystack_currency')) {
	function gd_client_portal_marketplace_get_paystack_currency()
	{
		$currency = get_option('gd_client_portal_paystack_currency', 'USD');
		$currency = strtoupper(sanitize_text_field($currency));
		return in_array($currency, array('NGN', 'USD', 'GHS', 'ZAR', 'KES', 'EUR', 'GBP'), true) ? $currency : 'USD';
	}
}

if (!function_exists('gd_client_portal_marketplace_get_paystack_config')) {
	function gd_client_portal_marketplace_get_paystack_config()
	{
		return array(
			'enabled' => get_option('gd_client_portal_paystack_enabled', '0') === '1',
			'currency' => gd_client_portal_marketplace_get_paystack_currency(),
			'public_key' => get_option('gd_client_portal_paystack_public_key', ''),
			'secret_key' => get_option('gd_client_portal_paystack_secret_key', ''),
			'webhook_secret' => get_option('gd_client_portal_paystack_webhook_secret', ''),
		);
	}
}

if (!function_exists('gd_client_portal_marketplace_get_single_item_data')) {
	function gd_client_portal_marketplace_get_single_item_data($id_or_sku)
	{
		$tenant_id = gd_client_portal_get_current_tenant_id();
		$items = gd_client_portal_filter_items_by_tenant(get_option('gd_client_portal_marketplace_items', array()), $tenant_id);
		if (!is_array($items)) {
			$items = array();
		}

		foreach ($items as $key => $item) {
			if (!is_array($item)) {
				continue;
			}
			if (($id_or_sku !== '') && ((string) ($item['id'] ?? '') === (string) $id_or_sku || (string) ($item['sku'] ?? '') === (string) $id_or_sku || (string) $key === (string) $id_or_sku)) {
				return $item;
			}
		}
		return array();
	}
}

if (!function_exists('gd_client_portal_marketplace_store_order_fallback')) {
	function gd_client_portal_marketplace_store_order_fallback($reference, $data)
	{
		$orders = get_option('gd_client_portal_marketplace_order_log', array());
		if (!is_array($orders)) {
			$orders = array();
		}
		$tenant_id = gd_client_portal_get_current_tenant_id();
		$orders[$reference] = array(
			'item' => $data['item'] ?? array(),
			'qty' => absint($data['qty'] ?? 1),
			'total' => !empty($data['total']) ? (float) $data['total'] : 0,
			'email' => sanitize_email($data['email'] ?? ''),
			'user_id' => get_current_user_id() ?: 0,
			'tenant_id' => $tenant_id,
			'created_at' => current_time('mysql'),
			'status' => 'pending',
		);
		update_option('gd_client_portal_marketplace_order_log', $orders);
		return $orders[$reference];
	}
}

if (!function_exists('gd_client_portal_marketplace_get_order_log_by_reference')) {
	function gd_client_portal_marketplace_get_order_log_by_reference($reference)
	{
		$orders = get_option('gd_client_portal_marketplace_order_log', array());
		if (!is_array($orders) || empty($orders[$reference])) {
			return array();
		}
		return $orders[$reference];
	}
}

if (!function_exists('gd_client_portal_marketplace_get_customer_orders')) {
	function gd_client_portal_marketplace_get_customer_orders($user_id = 0, $limit = 20)
	{
		$current_user = wp_get_current_user();
		$user_id = absint($user_id ?: get_current_user_id());
		$user_email = $current_user->exists() ? sanitize_email($current_user->user_email) : '';
		$current_tenant_id = gd_client_portal_get_current_tenant_id();
		$is_platform_admin = current_user_can('manage_options');
		$is_tenant_admin = gd_client_portal_user_is_tenant_admin() && !$is_platform_admin;
		$orders = array();

		// Tenant admins see all orders in their assigned tenant; ordinary clients
		// see only their own orders. Platform admins may see the full history.
		if ($is_tenant_admin && $current_tenant_id <= 0) {
			return array();
		}

		$query = array(
			'post_type' => 'gd_client_portal_marketplace_order',
			'post_status' => 'any',
			'posts_per_page' => max(1, absint($limit)),
			'orderby' => 'date',
			'order' => 'DESC',
		);

		if (!$is_platform_admin && !$is_tenant_admin) {
			if ($user_id) {
				$query['author'] = $user_id;
			}
		} elseif ($is_tenant_admin) {
			$query['meta_query'] = array(
				array(
					'key' => '_gd_mp_order_tenant_id',
					'value' => (string) $current_tenant_id,
					'compare' => '=',
				),
			);
		}

		$results = get_posts($query);
		foreach ($results as $order) {
			$meta_tenant_id = absint(get_post_meta($order->ID, '_gd_mp_order_tenant_id', true));

			if (!$is_platform_admin) {
				if ($current_tenant_id <= 0 || $meta_tenant_id !== $current_tenant_id) {
					continue;
				}
				if (!$is_tenant_admin && $user_id && absint($order->post_author) !== $user_id) {
					continue;
				}
			}

			$meta_email = get_post_meta($order->ID, '_gd_mp_order_email', true);
			if (!$is_tenant_admin && !$is_platform_admin && !empty($user_email) && !empty($meta_email) && sanitize_email($meta_email) !== $user_email) {
				continue;
			}
			$item = maybe_unserialize(get_post_meta($order->ID, '_gd_mp_order_item', true));
			$orders[] = array(
				'id' => absint($order->ID),
				'reference' => get_post_meta($order->ID, '_gd_mp_order_reference', true),
				'item' => is_array($item) && !empty($item['title']) ? $item['title'] : $order->post_title,
				'qty' => absint(get_post_meta($order->ID, '_gd_mp_order_qty', true)),
				'total' => get_post_meta($order->ID, '_gd_mp_order_total', true),
				'status' => get_post_meta($order->ID, '_gd_mp_order_status', true) ?: 'pending',
				'email' => !empty($meta_email) ? sanitize_email($meta_email) : $user_email,
				'created_at' => $order->post_date,
				'source' => 'post',
			);
		}

		$order_log = get_option('gd_client_portal_marketplace_order_log', array());
		if (is_array($order_log)) {
			foreach ($order_log as $reference => $entry) {
				if (!is_array($entry)) {
					continue;
				}
				$entry_tenant_id = absint($entry['tenant_id'] ?? 0);
				if (!$is_platform_admin) {
					if ($current_tenant_id <= 0 || $entry_tenant_id !== $current_tenant_id) {
						continue;
					}
					if (!$is_tenant_admin && $user_id && !empty($entry['user_id']) && absint($entry['user_id']) !== $user_id) {
						continue;
					}
				}
				$entry_email = sanitize_email($entry['email'] ?? '');
				if (!$is_tenant_admin && !$is_platform_admin && !empty($user_email) && !empty($entry_email) && $entry_email !== $user_email) {
					continue;
				}
				$item = is_array($entry['item'] ?? null) ? $entry['item'] : array();
				$orders[] = array(
					'id' => 0,
					'reference' => (string) $reference,
					'item' => !empty($item['title']) ? $item['title'] : __('Marketplace purchase', 'gd-client-portal'),
					'qty' => absint($entry['qty'] ?? 1),
					'total' => !empty($entry['total']) ? number_format((float) $entry['total'], 2, '.', '') : '0.00',
					'status' => !empty($entry['status']) ? sanitize_key($entry['status']) : 'pending',
					'email' => !empty($entry_email) ? $entry_email : $user_email,
					'created_at' => !empty($entry['created_at']) ? $entry['created_at'] : current_time('mysql'),
					'source' => 'log',
				);
			}
		}

		if (!empty($orders)) {
			usort($orders, function ($a, $b) {
				return strcmp(($b['created_at'] ?? ''), ($a['created_at'] ?? ''));
			});
			$orders = array_slice($orders, 0, max(1, absint($limit)));
		}

		return $orders;
	}
}

if (!function_exists('gd_client_portal_marketplace_render_order_history')) {
	function gd_client_portal_marketplace_render_order_history($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		$orders = gd_client_portal_marketplace_get_customer_orders();
		ob_start();
		echo '<section class="gd-market-orders" aria-label="Marketplace order history">';
		echo '<div class="gd-module-summary-row">';
		echo '<div><p class="gd-module-eyebrow">' . esc_html__('Purchase history', 'gd-client-portal') . '</p><h3>' . esc_html__('My orders', 'gd-client-portal') . '</h3></div>';
		echo '<span class="gd-pill">' . count($orders) . '</span>';
		echo '</div>';

		if (empty($orders)) {
			echo '<p class="gd-market-order-empty">' . esc_html__('You have not placed any marketplace orders yet.', 'gd-client-portal') . '</p>';
		} else {
			echo '<div class="gd-market-orders-table-wrap"><table class="gd-market-orders-table"><thead><tr><th>' . esc_html__('Order', 'gd-client-portal') . '</th><th>' . esc_html__('Item', 'gd-client-portal') . '</th><th>' . esc_html__('Qty', 'gd-client-portal') . '</th><th>' . esc_html__('Total', 'gd-client-portal') . '</th><th>' . esc_html__('Status', 'gd-client-portal') . '</th><th>' . esc_html__('Date', 'gd-client-portal') . '</th></tr></thead><tbody>';
			foreach ($orders as $order) {
				$order_id = !empty($order['id']) ? '#' . absint($order['id']) : (string) ($order['reference'] ?? '');
				$total_value = !empty($order['total']) ? '$' . number_format((float) $order['total'], 2) : '—';
				echo '<tr>';
				echo '<td>' . esc_html($order_id) . '</td>';
				echo '<td>' . esc_html($order['item'] ?? __('Marketplace purchase', 'gd-client-portal')) . '</td>';
				echo '<td>' . esc_html((string) ($order['qty'] ?? 1)) . '</td>';
				echo '<td>' . esc_html($total_value) . '</td>';
				echo '<td><span class="gd-market-order-status gd-market-order-status-' . esc_attr(strtolower((string) ($order['status'] ?? 'pending'))) . '">' . esc_html(ucfirst((string) ($order['status'] ?? 'pending'))) . '</span></td>';
				echo '<td>' . esc_html(mysql2date(get_option('date_format'), $order['created_at'] ?? current_time('mysql'))) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
		return ob_get_clean();
	}
}

if (!function_exists('gd_client_portal_marketplace_verify_paystack_reference')) {
	function gd_client_portal_marketplace_verify_paystack_reference($reference)
	{
		$config = gd_client_portal_marketplace_get_paystack_config();
		if (empty($config['secret_key']) || empty($reference)) {
			return array('success' => false, 'message' => __('Paystack verification is not configured.', 'gd-client-portal'));
		}

		$url = 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference);
		$response = wp_remote_get($url, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['secret_key'],
				'Accept' => 'application/json',
			),
		));

		if (is_wp_error($response)) {
			return array('success' => false, 'message' => $response->get_error_message());
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);
		if ($code >= 400 || empty($data['status']) || empty($data['data'])) {
			return array('success' => false, 'message' => !empty($data['message']) ? $data['message'] : __('Verification failed.', 'gd-client-portal'));
		}

		$status = strtolower((string) ($data['data']['status'] ?? ''));
		$orders = get_posts(array(
			'post_type' => 'gd_client_portal_marketplace_order',
			'posts_per_page' => 1,
			'meta_key' => '_gd_mp_order_reference',
			'meta_value' => sanitize_text_field($reference),
		));
		if (!empty($orders)) {
			$order_id = absint($orders[0]->ID);
			update_post_meta($order_id, '_gd_mp_order_payment_status', $status === 'success' ? 'paid' : 'failed');
			update_post_meta($order_id, '_gd_mp_order_status', $status === 'success' ? 'paid' : 'failed');
			if ($status === 'success') {
				update_post_meta($order_id, '_gd_mp_order_paid_at', current_time('mysql'));
			}
		} else {
			$logger = gd_client_portal_marketplace_get_order_log_by_reference($reference);
			if (!empty($logger)) {
				$logger['status'] = $status === 'success' ? 'paid' : 'failed';
				$orders_log = get_option('gd_client_portal_marketplace_order_log', array());
				if (is_array($orders_log)) {
					$orders_log[$reference] = $logger;
					update_option('gd_client_portal_marketplace_order_log', $orders_log);
				}
			}
		}

		return array(
			'success' => $status === 'success',
			'message' => $status === 'success' ? __('Payment confirmed.', 'gd-client-portal') : __('Payment is still pending or failed.', 'gd-client-portal'),
		);
	}
}

if (!function_exists('gd_client_portal_marketplace_create_order_record')) {
	function gd_client_portal_marketplace_create_order_record($item, $qty = 1, $user_email = '')
	{
		if (empty($item) || !is_array($item)) {
			error_log('[gd_client_portal_marketplace_create_order_record] empty item payload');
			return 0;
		}

		gd_client_portal_marketplace_register_order_type();

		$price = floatval($item['price'] ?? 0);
		$total = $price * max(1, absint($qty));
		$order_title = sprintf(__('Marketplace order %s', 'gd-client-portal'), current_time('timestamp'));
		$order_id = wp_insert_post(array(
			'post_type' => 'gd_client_portal_marketplace_order',
			'post_status' => 'pending',
			'post_author' => get_current_user_id() ?: 0,
			'post_title' => $order_title,
		));
		if (is_wp_error($order_id) || empty($order_id)) {
			$reference = 'GDM-' . get_current_blog_id() . '-' . wp_rand(100000, 9999999) . '-' . time();
			gd_client_portal_marketplace_store_order_fallback($reference, array(
				'item' => $item,
				'qty' => max(1, absint($qty)),
				'total' => (float) $total,
				'email' => sanitize_email($user_email ?: wp_get_current_user()->user_email ?: ''),
			));
			return array('order_id' => 0, 'reference' => $reference, 'amount' => round($total * 100), 'fallback' => true);
		}

		$reference = 'GDM-' . get_current_blog_id() . '-' . $order_id . '-' . wp_rand(1000, 999999);
		$tenant_id = gd_client_portal_get_current_tenant_id();
		update_post_meta($order_id, '_gd_mp_order_item', maybe_serialize($item));
		update_post_meta($order_id, '_gd_mp_order_qty', absint($qty));
		update_post_meta($order_id, '_gd_mp_order_total', number_format((float) $total, 2, '.', ''));
		update_post_meta($order_id, '_gd_mp_order_reference', $reference);
		update_post_meta($order_id, '_gd_mp_order_tenant_id', $tenant_id);
		update_post_meta($order_id, '_gd_mp_order_status', 'pending');
		update_post_meta($order_id, '_gd_mp_order_email', sanitize_email($user_email ?: wp_get_current_user()->user_email ?: ''));
		update_post_meta($order_id, '_gd_mp_order_currency', gd_client_portal_marketplace_get_paystack_currency());

		return array('order_id' => $order_id, 'reference' => $reference, 'amount' => round($total * 100));
	}
}

if (!function_exists('gd_client_portal_marketplace_handle_paystack_return')) {
	function gd_client_portal_marketplace_handle_paystack_return()
	{
		if (empty($_GET['gd_mp_paystack_status'])) {
			return;
		}

		$reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
		if (empty($reference)) {
			wp_safe_redirect(add_query_arg('gd_mp_status', 'failed', home_url('/marketplace/')));
			exit;
		}

		$result = gd_client_portal_marketplace_verify_paystack_reference($reference);
		$status = !empty($result['success']) ? 'success' : 'failed';
		$redirect_url = add_query_arg(array(
			'gd_mp_status' => $status,
			'reference' => $reference,
		), home_url('/marketplace/'));
		wp_safe_redirect($redirect_url);
		exit;
	}
	add_action('template_redirect', 'gd_client_portal_marketplace_handle_paystack_return');
}

if (!function_exists('gd_client_portal_marketplace_init_paystack_transaction')) {
	function gd_client_portal_marketplace_init_paystack_transaction($order_id, $reference, $amount, $email)
	{
		$config = gd_client_portal_marketplace_get_paystack_config();
		if (empty($config['enabled']) || empty($config['secret_key'])) {
			return array('success' => false, 'message' => __('Paystack is not configured.', 'gd-client-portal'));
		}

		$callback_url = add_query_arg(array('gd_mp_paystack_status' => 'success', 'reference' => $reference), home_url('/marketplace/'));
		$body = array(
			'email' => sanitize_email($email),
			'amount' => absint($amount),
			'reference' => sanitize_text_field($reference),
			'callback_url' => esc_url_raw($callback_url),
			'currency' => gd_client_portal_marketplace_get_paystack_currency(),
			'metadata' => array(
				'order_id' => absint($order_id),
				'plugin' => 'gd-client-portal',
			),
		);

		$response = wp_remote_post('https://api.paystack.co/transaction/initialize', array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $config['secret_key'],
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'body' => wp_json_encode($body),
		));

		if (is_wp_error($response)) {
			return array('success' => false, 'message' => $response->get_error_message());
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);
		if ($code >= 400 || empty($data['status']) || empty($data['data']['authorization_url'])) {
			return array('success' => false, 'message' => !empty($data['message']) ? $data['message'] : __('Payment initialization failed.', 'gd-client-portal'));
		}

		update_post_meta($order_id, '_gd_mp_order_paystack_reference', $reference);
		update_post_meta($order_id, '_gd_mp_order_paystack_url', esc_url_raw($data['data']['authorization_url']));

		return array(
			'success' => true,
			'authorization_url' => esc_url_raw($data['data']['authorization_url']),
			'reference' => sanitize_text_field($reference),
		);
	}
}

	// REST API for marketplace items (load-more)
	if (!function_exists('gd_client_portal_marketplace_register_rest')) {
		function gd_client_portal_marketplace_register_rest() {
			register_rest_route('gd-client-portal/v1', '/marketplace', array(
				'methods' => 'GET',
				'callback' => 'gd_client_portal_marketplace_rest_items',
				'permission_callback' => function () { return gd_client_portal_verify_request(); },
				'args' => array(
					'page' => array('validate_callback' => 'is_numeric'),
					'perpage' => array('validate_callback' => 'is_numeric'),
					'cat' => array('sanitize_callback' => 'sanitize_text_field')
				)
			));
			register_rest_route('gd-client-portal/v1', '/paystack-webhook', array(
				'methods' => 'POST',
				'callback' => 'gd_client_portal_marketplace_paystack_webhook',
				'permission_callback' => '__return_true',
			));
		}
		add_action('rest_api_init', 'gd_client_portal_marketplace_register_rest');
	}

	if (!function_exists('gd_client_portal_marketplace_rest_items')) {
		function gd_client_portal_marketplace_rest_items($request) {
			$page = max(1, intval($request->get_param('page') ?: 1));
			$perpage = max(1, intval($request->get_param('perpage') ?: 6));
			$cat = $request->get_param('cat') ?: '';

			$items = gd_client_portal_filter_items_by_tenant(get_option('gd_client_portal_marketplace_items', array()), gd_client_portal_get_current_tenant_id());
			if (!is_array($items)) { $items = array(); }
			$visible = array_filter($items, function($i){ return !empty($i['enabled']); });

			$filtered = array();
			foreach ($visible as $it) {
				if ($cat) {
					if (empty($it['category']) || strval($it['category']) !== strval($cat)) { continue; }
				}
				$filtered[] = $it;
			}

			$total = count($filtered);
			$pages = max(1, ceil($total / $perpage));
			if ($page > $pages) { $page = $pages; }
			$start = ($page - 1) * $perpage;
			$page_items = array_slice($filtered, $start, $perpage);

			$out = array('page'=>$page,'perpage'=>$perpage,'pages'=>$pages,'total'=>$total,'items'=>array());
			foreach ($page_items as $it) {
				$img = '';
				if (!empty($it['image_id'])) { $img = wp_get_attachment_image_url($it['image_id'],'medium'); }
				$out['items'][] = array(
					'id' => $it['id'],
					'title' => $it['title'],
					'description' => $it['description'],
					'price' => floatval($it['price']),
					'sku' => $it['sku'],
					'wc_product_id' => !empty($it['wc_product_id']) ? intval($it['wc_product_id']) : 0,
					'image' => $img,
					'category' => $it['category'] ?? '',
				);
			}
			return rest_ensure_response($out);
		}
	}

if (!function_exists('gd_client_portal_render_marketplace')) {
	function gd_client_portal_render_marketplace($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-marketplace');
		wp_enqueue_script('gd-client-portal-marketplace');
		$config = gd_client_portal_marketplace_get_paystack_config();
		wp_localize_script('gd-client-portal-marketplace', 'gdClientPortalMarketplace', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'rest_base' => esc_url_raw(rest_url('gd-client-portal/v1/marketplace')),
			'nonce' => wp_create_nonce('gd_client_portal_marketplace_add_to_cart'),
			'paystack_enabled' => !empty($config['enabled']) ? 1 : 0,
		));
		// enqueue native bridge script if present
		wp_enqueue_script('gd-client-portal-marketplace-native');
		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/marketplace/templates/detail.php';
		if (file_exists($tpl)) {
			ob_start(); include $tpl; return ob_get_clean();
		}
		return '<p>' . esc_html__('Marketplace item unavailable.', 'gd-client-portal') . '</p>';
	}

	// AJAX add-to-cart handler
	if (!function_exists('gd_client_portal_marketplace_add_to_cart')) {
		function gd_client_portal_marketplace_add_to_cart()
		{
			if (!gd_client_portal_verify_request()) {
				wp_send_json_error(array('message' => __('Please log in to continue.', 'gd-client-portal')));
			}

			// Validate nonce (accept either 'nonce' or 'security')
			$nonce = '';
			if (!empty($_REQUEST['nonce'])) { $nonce = wp_unslash($_REQUEST['nonce']); }
			if (empty($nonce) && !empty($_REQUEST['security'])) { $nonce = wp_unslash($_REQUEST['security']); }
			if (empty($nonce) || !wp_verify_nonce($nonce, 'gd_client_portal_marketplace_add_to_cart')) {
				error_log('[gd_client_portal_marketplace_add_to_cart] invalid nonce');
				wp_send_json_error(array('message' => __('Invalid request', 'gd-client-portal')));
			}

			if (!gd_client_portal_verify_ajax_tenant('tenant_id')) {
				wp_send_json_error(array('message' => __('You do not have permission for this tenant.', 'gd-client-portal')));
			}

			// Accept either id or sku
			$raw_id = isset($_REQUEST['id']) ? sanitize_text_field(wp_unslash($_REQUEST['id'])) : '';
			$raw_sku = isset($_REQUEST['sku']) ? sanitize_text_field(wp_unslash($_REQUEST['sku'])) : '';

			$items = gd_client_portal_filter_items_by_tenant(get_option('gd_client_portal_marketplace_items', array()), gd_client_portal_get_current_tenant_id());
			if (!is_array($items)) { $items = array(); }

			$it = null;
			$found_key = null;
			// direct key lookup
			if ($raw_id && isset($items[$raw_id])) {
				$it = $items[$raw_id];
				$found_key = $raw_id;
			} else {
				// search by item 'id' field or sku
				foreach ($items as $k => $v) {
					if ($raw_id && isset($v['id']) && strval($v['id']) === strval($raw_id)) { $it = $v; $found_key = $k; break; }
					if ($raw_sku && isset($v['sku']) && strval($v['sku']) === strval($raw_sku)) { $it = $v; $found_key = $k; break; }
				}
			}

			if (empty($it) || empty($it['enabled'])) {
				wp_send_json_error(array('message' => __('Item not found or not available', 'gd-client-portal')));
			}

			// Determine product id: prefer mapped WC product, otherwise try SKU lookup
			$product_id = 0;
			if (!empty($it['wc_product_id'])) { $product_id = absint($it['wc_product_id']); }
			if (empty($product_id) && !empty($it['sku']) && function_exists('wc_get_product_id_by_sku')) {
				$product_id = wc_get_product_id_by_sku($it['sku']);
			}

			if (empty($product_id)) {
				// attempt to create a simple WooCommerce product on-the-fly if possible
				if (function_exists('wp_insert_post')) {
					$post = array(
						'post_title' => $it['title'] ?? '',
						'post_content' => $it['description'] ?? '',
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
						$price_val = floatval($it['price'] ?? 0);
						if (function_exists('wc_format_decimal')) { $price_val = wc_format_decimal($price_val); }
						update_post_meta($post_id, '_regular_price', $price_val);
						update_post_meta($post_id, '_price', $price_val);
						update_post_meta($post_id, '_stock_status', 'instock');
						// set featured image if provided
						if (!empty($it['image_id']) && function_exists('set_post_thumbnail')) {
							set_post_thumbnail($post_id, absint($it['image_id']));
						}
						// persist mapping back to options if we know the key
						if ($found_key) {
							$items[$found_key]['wc_product_id'] = absint($post_id);
							update_option('gd_client_portal_marketplace_items', $items);
						}
						$product_id = absint($post_id);
					}
				}
				if (empty($product_id)) {
					error_log(sprintf('[gd_client_portal_marketplace_add_to_cart] no product_id for item id=%s sku=%s', $it['id'] ?? '', $it['sku'] ?? ''));
					wp_send_json_error(array('message' => __('No matching WooCommerce product configured for this item.', 'gd-client-portal')));
				}
			}

			if (!function_exists('WC') || !WC()->cart) {
				error_log('[gd_client_portal_marketplace_add_to_cart] WooCommerce not available in AJAX context');
				wp_send_json_error(array('message' => __('WooCommerce is not available.', 'gd-client-portal')));
			}

			$added = WC()->cart->add_to_cart($product_id);
			if ($added) {
				// return cart URL, cart item key and current cart count
				$cart_count = 0;
				if (WC() && WC()->cart) {
					$cart_count = intval(WC()->cart->get_cart_contents_count());
				}
				wp_send_json_success(array('redirect' => wc_get_cart_url(), 'cart_item_key' => $added, 'cart_count' => $cart_count, 'message' => __('Added to cart', 'gd-client-portal')));
			}

			error_log(sprintf('[gd_client_portal_marketplace_add_to_cart] WC add_to_cart failed for product %s (item id=%s)', $product_id, $it['id'] ?? ''));
			wp_send_json_error(array('message' => __('Failed to add to cart', 'gd-client-portal')));
		}
		add_action('wp_ajax_gd_client_portal_marketplace_add_to_cart', 'gd_client_portal_marketplace_add_to_cart');
	}
}

if (!function_exists('gd_client_portal_marketplace_paystack_webhook')) {
	function gd_client_portal_marketplace_paystack_webhook($request = null)
	{
		$config = gd_client_portal_marketplace_get_paystack_config();
		$raw_body = file_get_contents('php://input');
		if (empty($raw_body)) {
			return rest_ensure_response(array('status' => false, 'message' => 'Empty payload'), 400);
		}

		$signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) : '';
		if (empty($config['webhook_secret']) || empty($signature) || !hash_equals(hash_hmac('sha512', $raw_body, $config['webhook_secret']), $signature)) {
			return rest_ensure_response(array('status' => false, 'message' => 'Invalid signature'), 401);
		}

		$payload = json_decode($raw_body, true);
		$event = !empty($payload['event']) ? sanitize_text_field($payload['event']) : '';
		$data = is_array($payload['data'] ?? null) ? $payload['data'] : array();
		$reference = !empty($data['reference']) ? sanitize_text_field($data['reference']) : '';
		if (empty($reference) || !in_array($event, array('charge.success', 'transaction.success'), true)) {
			return rest_ensure_response(array('status' => true, 'message' => 'Ignored event'));
		}

		$orders = get_posts(array(
			'post_type' => 'gd_client_portal_marketplace_order',
			'posts_per_page' => 1,
			'meta_key' => '_gd_mp_order_reference',
			'meta_value' => $reference,
		));
		if (!empty($orders)) {
			$order_id = absint($orders[0]->ID);
			update_post_meta($order_id, '_gd_mp_order_status', 'paid');
			update_post_meta($order_id, '_gd_mp_order_payment_status', 'paid');
			update_post_meta($order_id, '_gd_mp_order_paid_at', current_time('mysql'));
		}

		return rest_ensure_response(array('status' => true, 'message' => 'Payment status updated'));
	}
}

if (!function_exists('gd_client_portal_marketplace_create_paystack_checkout')) {
	function gd_client_portal_marketplace_create_paystack_checkout()
	{
		if (!gd_client_portal_verify_request()) {
			wp_send_json_error(array('message' => __('Please log in to continue.', 'gd-client-portal')));
		}

		$nonce = isset($_REQUEST['nonce']) ? wp_unslash($_REQUEST['nonce']) : '';
		if (empty($nonce) || !wp_verify_nonce($nonce, 'gd_client_portal_marketplace_add_to_cart')) {
			wp_send_json_error(array('message' => __('Invalid request.', 'gd-client-portal')));
		}

		if (!gd_client_portal_verify_ajax_tenant('tenant_id')) {
			wp_send_json_error(array('message' => __('You do not have permission for this tenant.', 'gd-client-portal')));
		}

		$item_id = isset($_REQUEST['id']) ? sanitize_text_field(wp_unslash($_REQUEST['id'])) : '';
		$sku = isset($_REQUEST['sku']) ? sanitize_text_field(wp_unslash($_REQUEST['sku'])) : '';
		$item = gd_client_portal_marketplace_get_single_item_data($item_id ?: $sku);
		if (empty($item) || empty($item['enabled'])) {
			wp_send_json_error(array('message' => __('Item not found or not available.', 'gd-client-portal')));
		}

		// Ensure the item is accessible under the current tenant
		$item_tenant = absint($item['tenant_id'] ?? 0);
		if (!gd_client_portal_verify_tenant_access($item_tenant)) {
			wp_send_json_error(array('message' => __('You do not have permission to purchase this item.', 'gd-client-portal')));
		}

		$email = isset($_REQUEST['email']) ? sanitize_email(wp_unslash($_REQUEST['email'])) : '';
		if (empty($email)) {
			$current_user = wp_get_current_user();
			$email = $current_user->exists() ? $current_user->user_email : '';
		}
		if (empty($email)) {
			wp_send_json_error(array('message' => __('A valid email is required to continue.', 'gd-client-portal')));
		}

		$config = gd_client_portal_marketplace_get_paystack_config();
		if (empty($config['enabled']) || empty($config['secret_key'])) {
			wp_send_json_error(array('message' => __('Paystack is not configured yet.', 'gd-client-portal')));
		}

		$qty = max(1, absint($_REQUEST['quantity'] ?? 1));
		$created = gd_client_portal_marketplace_create_order_record($item, $qty, $email);
		if (empty($created) || empty($created['reference'])) {
			wp_send_json_error(array('message' => __('Could not create your marketplace order.', 'gd-client-portal')));
		}

		$order_id_for_meta = !empty($created['order_id']) ? absint($created['order_id']) : 0;
		$init = gd_client_portal_marketplace_init_paystack_transaction($order_id_for_meta ?: 0, $created['reference'], $created['amount'], $email);
		if (empty($init['success'])) {
			wp_send_json_error(array('message' => $init['message'] ?? __('Payment could not be initialized.', 'gd-client-portal')));
		}

		wp_send_json_success(array(
			'redirect' => $init['authorization_url'],
			'order_id' => $created['order_id'],
			'reference' => $init['reference'],
		));
	}
	add_action('wp_ajax_gd_client_portal_marketplace_create_paystack_checkout', 'gd_client_portal_marketplace_create_paystack_checkout');
}

// AJAX endpoint to return current cart count (useful for header sync)
if (!function_exists('gd_client_portal_marketplace_get_cart_count')) {
	function gd_client_portal_marketplace_get_cart_count()
	{
		if (!function_exists('WC') || !WC() || !WC()->cart) {
			wp_send_json_success(array('cart_count' => 0));
		}
		$count = intval(WC()->cart->get_cart_contents_count());
		wp_send_json_success(array('cart_count' => $count));
	}
	add_action('wp_ajax_gd_client_portal_marketplace_get_cart_count', 'gd_client_portal_marketplace_get_cart_count');
	add_action('wp_ajax_nopriv_gd_client_portal_marketplace_get_cart_count', 'gd_client_portal_marketplace_get_cart_count');
}

add_action('init', 'gd_client_portal_register_marketplace_module');

// Register marketplace module dashboard views
if (!function_exists('gd_client_portal_register_marketplace_dashboard_view')) {
	function gd_client_portal_register_marketplace_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('marketplace', 'gd_client_portal_render_marketplace');
			gd_client_portal_register_dashboard_view('marketplace-orders', 'gd_client_portal_marketplace_render_order_history');
		}
	}
	add_action('init', 'gd_client_portal_register_marketplace_dashboard_view');
}
