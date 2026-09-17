<?php
/**
 * Account module placeholder.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('gd_client_portal_register_account_module')) {
	function gd_client_portal_register_account_module()
	{
		add_shortcode('gd_client_portal_account', 'gd_client_portal_render_account');
		add_action('wp_enqueue_scripts', 'gd_client_portal_register_account_assets');
	}
}

if (!function_exists('gd_client_portal_register_account_assets')) {
	function gd_client_portal_register_account_assets()
	{
		wp_register_style('gd-client-portal-account', GD_CLIENT_PORTAL_URL . 'modules/account/assets/account.css', array('gd-client-portal'), GD_CLIENT_PORTAL_VERSION);
		wp_register_script('gd-client-portal-account', GD_CLIENT_PORTAL_URL . 'modules/account/assets/account.js', array('gd-client-portal', 'jquery'), GD_CLIENT_PORTAL_VERSION, true);
		wp_localize_script('gd-client-portal-account', 'gdClientPortalAccount', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'avatar_nonce' => wp_create_nonce('gd_client_portal_avatar_upload'),
			'update_nonce' => wp_create_nonce('gd_client_portal_account_update_ajax'),
		));
	}
}

if (!function_exists('gd_client_portal_render_account')) {
	function gd_client_portal_render_account($atts = array())
	{
		if (!gd_client_portal_verify_request()) {
			return gd_client_portal_render_access_gate();
		}

		if (!gd_client_portal_verify_tenant_access()) {
			return gd_client_portal_render_access_gate();
		}

		wp_enqueue_style('gd-client-portal-account');
		// Ensure WordPress media library scripts are available for the media modal
		if (function_exists('wp_enqueue_media')) {
			wp_enqueue_media();
		}
		wp_enqueue_script('gd-client-portal-account');

		$user_id = get_current_user_id();
		if (empty($user_id)) {
			return '<p>' . esc_html__('You must be logged in to view your account.', 'gd-client-portal') . '</p>';
		}

		$user = get_userdata($user_id);
		if (!$user) {
			return '<p>' . esc_html__('User not found.', 'gd-client-portal') . '</p>';
		}

		$tpl = GD_CLIENT_PORTAL_PATH . 'modules/account/templates/profile.php';
		if (file_exists($tpl)) {
			ob_start();
			include $tpl;
			return ob_get_clean();
		}

		$out = '<div class="gd-account">';
		$out .= '<h3>' . esc_html(sprintf(__('%s\'s Profile', 'gd-client-portal'), $user->display_name)) . '</h3>';
		$out .= '<p><strong>' . esc_html__('Email:', 'gd-client-portal') . '</strong> ' . esc_html($user->user_email) . '</p>';
		$out .= '<p><strong>' . esc_html__('Username:', 'gd-client-portal') . '</strong> ' . esc_html($user->user_login) . '</p>';
		$roles = implode(', ', $user->roles ?: array());
		$out .= '<p><strong>' . esc_html__('Roles:', 'gd-client-portal') . '</strong> ' . esc_html($roles) . '</p>';
		$out .= '<p><a href="' . esc_url(get_edit_profile_url($user_id)) . '">' . esc_html__('Edit profile', 'gd-client-portal') . '</a></p>';
		$out .= '</div>';

		return $out;
	}
}

add_action('init', 'gd_client_portal_register_account_module');

// Register account module dashboard view if registry is available
if (!function_exists('gd_client_portal_register_account_dashboard_view')) {
	function gd_client_portal_register_account_dashboard_view()
	{
		if (function_exists('gd_client_portal_register_dashboard_view')) {
			gd_client_portal_register_dashboard_view('account', 'gd_client_portal_render_account');
		}
	}
	add_action('init', 'gd_client_portal_register_account_dashboard_view');
}

// Handle account updates (profile fields + avatar upload)
if (!function_exists('gd_client_portal_handle_account_update')) {
	function gd_client_portal_handle_account_update()
	{
		if (empty($_POST['gd_client_portal_update_account'])) {
			return;
		}

		if (!is_user_logged_in()) {
			wp_die(esc_html__('You must be logged in to perform this action.', 'gd-client-portal'));
		}

		if (!isset($_POST['gd_client_portal_update_account_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_update_account_nonce']), 'gd_client_portal_update_account')) {
			wp_die(esc_html__('Invalid request.', 'gd-client-portal'));
		}

		$user_id = get_current_user_id();

		// Sanitize and save fields (user meta)
		$fields = array(
			'billing_email' => 'sanitize_email',
			'billing_phone' => 'sanitize_text_field',
			'company_name' => 'sanitize_text_field',
			'company_address' => 'sanitize_textarea_field',
			'contact_person' => 'sanitize_text_field',
			'preferred_contact_method' => 'sanitize_text_field',
			'account_notes' => 'sanitize_textarea_field',
		);

		foreach ($fields as $key => $sanitizer) {
			$val = isset($_POST[$key]) ? call_user_func($sanitizer, wp_unslash($_POST[$key])) : '';
			if ($val === '') {
				delete_user_meta($user_id, $key);
			} else {
				update_user_meta($user_id, $key, $val);
			}
		}

		// Save common WP user fields if provided
		$user_update = array('ID' => $user_id);
		if (isset($_POST['display_name'])) {
			$user_update['display_name'] = sanitize_text_field(wp_unslash($_POST['display_name']));
		}
		if (isset($_POST['first_name'])) {
			$user_update['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
		}
		if (isset($_POST['last_name'])) {
			$user_update['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
		}
		if (isset($_POST['user_email'])) {
			$email = sanitize_email(wp_unslash($_POST['user_email']));
			if (!empty($email)) {
				$user_update['user_email'] = $email;
			}
		}
		if (count($user_update) > 1) {
			wp_update_user($user_update);
		}

		// Handle avatar upload
		if (!empty($_FILES['gd_client_portal_avatar']) && !empty($_FILES['gd_client_portal_avatar']['tmp_name'])) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$file = &$_FILES['gd_client_portal_avatar'];
			if ($file && !empty($file['tmp_name'])) {
				$overrides = array('test_form' => false);
				$movefile = wp_handle_upload($file, $overrides);
				if (isset($movefile['file'])) {
					$filename = $movefile['file'];
					$filetype = wp_check_filetype(basename($filename), null);
					$attachment = array(
						'post_mime_type' => $filetype['type'],
						'post_title' => sanitize_file_name(basename($filename)),
						'post_content' => '',
						'post_status' => 'inherit'
					);
					$attach_id = wp_insert_attachment($attachment, $filename);
					if (!is_wp_error($attach_id)) {
						$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
						wp_update_attachment_metadata($attach_id, $attach_data);
						update_user_meta($user_id, 'gd_client_portal_avatar_id', $attach_id);
					}
				}
			}
		}

		// Redirect back with success flag
		$ref = wp_get_referer() ?: home_url();
		wp_safe_redirect(add_query_arg('gd_client_portal_account_updated', '1', $ref));
		exit;
	}
	add_action('init', 'gd_client_portal_handle_account_update');
}

// AJAX handler for avatar upload
if (!function_exists('gd_client_portal_ajax_avatar_upload')) {
	function gd_client_portal_ajax_avatar_upload()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => 'Not logged in'), 403);
		}

		check_ajax_referer('gd_client_portal_avatar_upload', 'nonce');

		if (empty($_FILES['avatar']) || empty($_FILES['avatar']['tmp_name'])) {
			wp_send_json_error(array('message' => 'No file uploaded'), 400);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file = &$_FILES['avatar'];
		$overrides = array('test_form' => false);
		$movefile = wp_handle_upload($file, $overrides);
		if (isset($movefile['file'])) {
			$filename = $movefile['file'];
			$filetype = wp_check_filetype(basename($filename), null);
			$attachment = array(
				'post_mime_type' => $filetype['type'],
				'post_title' => sanitize_file_name(basename($filename)),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attach_id = wp_insert_attachment($attachment, $filename);
			if (!is_wp_error($attach_id)) {
				$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
				wp_update_attachment_metadata($attach_id, $attach_data);
				$user_id = get_current_user_id();
				update_user_meta($user_id, 'gd_client_portal_avatar_id', $attach_id);
				$url = wp_get_attachment_image_url($attach_id, 'thumbnail');
				wp_send_json_success(array('url' => $url, 'attach_id' => $attach_id));
			}
		}

		wp_send_json_error(array('message' => 'Upload failed'), 500);
	}
	add_action('wp_ajax_gd_client_portal_avatar_upload', 'gd_client_portal_ajax_avatar_upload');
}

	// AJAX handler: account update (profile save via AJAX)
	if (!function_exists('gd_client_portal_ajax_account_update')) {
		function gd_client_portal_ajax_account_update()
		{
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => 'Not logged in'), 403);
			}

			check_ajax_referer('gd_client_portal_account_update_ajax', 'nonce');

			$user_id = get_current_user_id();

			// Sanitize and save fields (user meta)
			$fields = array(
				'billing_email' => 'sanitize_email',
				'billing_phone' => 'sanitize_text_field',
				'company_name' => 'sanitize_text_field',
				'company_address' => 'sanitize_textarea_field',
				'contact_person' => 'sanitize_text_field',
				'preferred_contact_method' => 'sanitize_text_field',
				'account_notes' => 'sanitize_textarea_field',
			);

			foreach ($fields as $key => $sanitizer) {
				$val = isset($_POST[$key]) ? call_user_func($sanitizer, wp_unslash($_POST[$key])) : '';
				if ($val === '') {
					delete_user_meta($user_id, $key);
				} else {
					update_user_meta($user_id, $key, $val);
				}
			}

			// Save common WP user fields if provided
			$user_update = array('ID' => $user_id);
			if (isset($_POST['display_name'])) {
				$user_update['display_name'] = sanitize_text_field(wp_unslash($_POST['display_name']));
			}
			if (isset($_POST['first_name'])) {
				$user_update['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name']));
			}
			if (isset($_POST['last_name'])) {
				$user_update['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name']));
			}
			if (isset($_POST['user_email'])) {
				$email = sanitize_email(wp_unslash($_POST['user_email']));
				if (!empty($email)) {
					$user_update['user_email'] = $email;
				}
			}
			if (count($user_update) > 1) {
				wp_update_user($user_update);
			}

			// Handle avatar upload via AJAX (if file is sent)
			if (!empty($_FILES['gd_client_portal_avatar']) && !empty($_FILES['gd_client_portal_avatar']['tmp_name'])) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$file = &$_FILES['gd_client_portal_avatar'];
				if ($file && !empty($file['tmp_name'])) {
					$overrides = array('test_form' => false);
					$movefile = wp_handle_upload($file, $overrides);
					if (isset($movefile['file'])) {
						$filename = $movefile['file'];
						$filetype = wp_check_filetype(basename($filename), null);
						$attachment = array(
							'post_mime_type' => $filetype['type'],
							'post_title' => sanitize_file_name(basename($filename)),
							'post_content' => '',
							'post_status' => 'inherit'
						);
						$attach_id = wp_insert_attachment($attachment, $filename);
						if (!is_wp_error($attach_id)) {
							$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
							wp_update_attachment_metadata($attach_id, $attach_data);
							update_user_meta($user_id, 'gd_client_portal_avatar_id', $attach_id);
						}
					}
				}
			}

			// Return success and optionally updated fields for UI refresh
			$avatar_id = get_user_meta($user_id, 'gd_client_portal_avatar_id', true);
			$avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
			wp_send_json_success(array('message' => 'Profile updated', 'avatar_url' => $avatar_url));
		}
		add_action('wp_ajax_gd_client_portal_account_update', 'gd_client_portal_ajax_account_update');
	}

// AJAX handler: set avatar by existing attachment ID
if (!function_exists('gd_client_portal_ajax_avatar_set')) {
	function gd_client_portal_ajax_avatar_set()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => 'Not logged in'), 403);
		}
		check_ajax_referer('gd_client_portal_avatar_upload', 'nonce');
		$attach_id = isset($_POST['attach_id']) ? absint($_POST['attach_id']) : 0;
		if (!$attach_id || !get_post($attach_id)) {
			wp_send_json_error(array('message' => 'Invalid attachment'), 400);
		}
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'gd_client_portal_avatar_id', $attach_id);
		$url = wp_get_attachment_image_url($attach_id, 'thumbnail');
		wp_send_json_success(array('url' => $url, 'attach_id' => $attach_id));
	}
	add_action('wp_ajax_gd_client_portal_avatar_set', 'gd_client_portal_ajax_avatar_set');
}

// AJAX handler: remove avatar association
if (!function_exists('gd_client_portal_ajax_avatar_remove')) {
	function gd_client_portal_ajax_avatar_remove()
	{
		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => 'Not logged in'), 403);
		}
		check_ajax_referer('gd_client_portal_avatar_upload', 'nonce');
		$user_id = get_current_user_id();
		$attach_id = get_user_meta($user_id, 'gd_client_portal_avatar_id', true);
		delete_user_meta($user_id, 'gd_client_portal_avatar_id');

		// Optionally delete the attachment from media library if requested and current user can delete it
		$delete = isset($_POST['delete']) ? boolval($_POST['delete']) : false;
		if ($delete && $attach_id && get_post($attach_id)) {
			if (current_user_can('delete_post', $attach_id)) {
				wp_delete_attachment($attach_id, true);
			}
		}

		wp_send_json_success();
	}
	add_action('wp_ajax_gd_client_portal_avatar_remove', 'gd_client_portal_ajax_avatar_remove');
}

if (!function_exists('gd_client_portal_module_account_activate')) {
	function gd_client_portal_module_account_activate()
	{
		update_option('gd_client_portal_module_account_installed', current_time('mysql'));
	}
}

if (!function_exists('gd_client_portal_module_account_deactivate')) {
	function gd_client_portal_module_account_deactivate()
	{
		update_option('gd_client_portal_module_account_deactivated', current_time('mysql'));
	}
}
