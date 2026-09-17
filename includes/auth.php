<?php
/**
 * Authentication UI for the GD Client Portal plugin.
 * Self-contained login and registration (no WooCommerce dependency).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('gd_client_portal_render_auth_ui')) {
    function gd_client_portal_render_auth_ui($atts = array())
    {
        // Render any auth messages passed via query args
        $auth_error = '';
        $auth_success = '';
        if (!empty($_GET['gd_auth_error'])) {
            $auth_error = wp_kses_post(urldecode(wp_unslash($_GET['gd_auth_error'])));
        }
        if (!empty($_GET['gd_auth_success'])) {
            $auth_success = wp_kses_post(urldecode(wp_unslash($_GET['gd_auth_success'])));
        }
        
        return gd_client_portal_render_auth_ui_html($auth_error, $auth_success, $atts);
    }
}

if (!function_exists('gd_client_portal_render_auth_ui_html')) {
    function gd_client_portal_render_auth_ui_html($auth_error = '', $auth_success = '', $atts = array())
    {
        // detect post-purchase redirect context (from WooCommerce thankyou)
        $gd_post_purchase = !empty($_REQUEST['gd_post_purchase']) ? sanitize_text_field(wp_unslash($_REQUEST['gd_post_purchase'])) : '';
        $gd_order_id = !empty($_REQUEST['order_id']) ? absint(wp_unslash($_REQUEST['order_id'])) : 0;
        // Inline login form and a custom registration form (no WooCommerce required)
        $login_form = '';
        $login_form .= '<form method="post" action="" class="gd-client-portal-login-form" style="display:block;max-width:100%;margin:0 auto;">';
        ob_start();
        wp_nonce_field('gd_client_portal_login', 'gd_client_portal_login_nonce');
        $nonce_field = ob_get_clean();
        $login_form .= $nonce_field;
        if (!empty($gd_post_purchase)) {
            $login_form .= '<input type="hidden" name="gd_post_purchase" value="1" />';
            if (!empty($gd_order_id)) {
                $login_form .= '<input type="hidden" name="gd_order_id" value="' . intval($gd_order_id) . '" />';
            }
        }
        $login_form .= '<div style="margin-bottom:14px;"><label for="gd_login_user" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;">' . esc_html__('Email or username', 'gd-client-portal') . ' <span class="required" style="color:#dc2626;">*</span></label>';
        $login_form .= '<input type="text" class="input-text" name="gd_login_user" id="gd_login_user" autocomplete="username" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" /></div>';
        $login_form .= '<div style="margin-bottom:14px;"><label for="gd_login_pass" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;">' . esc_html__('Password', 'gd-client-portal') . ' <span class="required" style="color:#dc2626;">*</span></label>';
        $login_form .= '<input type="password" class="input-text" name="gd_login_pass" id="gd_login_pass" autocomplete="current-password" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" /></div>';
        $login_form .= '<div style="margin-bottom:14px;"><label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:#374151;"><input type="checkbox" name="gd_login_remember" value="1" style="margin:0;"> ' . esc_html__('Remember me', 'gd-client-portal') . '</label></div>';
        $login_form .= '<p class="form-row form-row-wide" style="margin:0;"><button type="submit" class="button button-primary" name="gd_client_portal_login" value="1" style="display:block;width:100%;padding:12px 16px;border:none;border-radius:10px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;">' . esc_html__('Log In', 'gd-client-portal') . '</button></p>';
        $login_form .= '</form>';

        ob_start();
        ?>
        <div class="gd-auth-shell" data-gd-auth-shell>
            <div class="gd-auth-card">
                <div class="gd-auth-tabs" role="tablist" aria-label="Client authentication">
                    <button type="button" class="gd-auth-tab is-active" data-gd-auth-tab="login">
                        <?php esc_html_e('Log In', 'gd-client-portal'); ?>
                    </button>
                    <button type="button" class="gd-auth-tab" data-gd-auth-tab="register">
                        <?php esc_html_e('Create Account', 'gd-client-portal'); ?>
                    </button>
                </div>

                <div class="gd-auth-panel is-active" data-gd-auth-panel="login">
                    <h3><?php esc_html_e('Welcome back', 'gd-client-portal'); ?></h3>
                    <p><?php esc_html_e('Access your client dashboard with your account.', 'gd-client-portal'); ?></p>
                    <?php if (!empty($auth_error)) : ?>
                        <div class="gd-auth-error"><?php echo $auth_error; ?></div>
                    <?php elseif (!empty($auth_success)) : ?>
                        <div class="gd-auth-success"><?php echo $auth_success; ?></div>
                    <?php endif; ?>
                    <?php
                    // If the visitor is already logged in, show a link to their dashboard instead of the login form
                    if (is_user_logged_in()) {
                        $current_user = wp_get_current_user();
                        $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
                        if (empty($dash)) {
                            $dash = home_url('/');
                        }
                        ?>
                        <div style="margin:18px 0;padding:12px;border-radius:8px;background:#ecfdf5;border:1px solid #bbf7d0;">
                            <p style="margin:0 0 8px;font-weight:600;color:#065f46;"><?php echo sprintf(esc_html__('You are logged in as %s.', 'gd-client-portal'), esc_html($current_user->display_name)); ?></p>
                            <p style="margin:0;"><a class="button button-primary" href="<?php echo esc_url($dash); ?>" style="display:inline-block;padding:10px 14px;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;"><?php esc_html_e('Go to your dashboard', 'gd-client-portal'); ?></a></p>
                        </div>
                        <?php
                    } else {
                        echo $login_form; // safe: constructed markup
                    }
                    ?>
                </div>

                <div class="gd-auth-panel" data-gd-auth-panel="register">
                    <h3><?php esc_html_e('Create your client account', 'gd-client-portal'); ?></h3>
                    <p><?php esc_html_e('Create a premium client account to unlock projects, documents, and workflow updates.', 'gd-client-portal'); ?></p>
                    <form method="post" action="" class="gd-client-portal-register-form" style="display:block;max-width:100%;margin:0 auto;">
                        <?php if (!empty($auth_error)) : ?>
                            <div class="gd-auth-error"><?php echo $auth_error; ?></div>
                        <?php elseif (!empty($auth_success)) : ?>
                            <div class="gd-auth-success"><?php echo $auth_success; ?></div>
                        <?php endif; ?>
                        <?php if (is_user_logged_in()) :
                            // If logged in, don't show registration fields — provide dashboard link only
                            $current_user = wp_get_current_user();
                            $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
                            if (empty($dash)) {
                                $dash = home_url('/');
                            }
                        ?>
                            <div style="margin:18px 0;padding:12px;border-radius:8px;background:#ecfdf5;border:1px solid #bbf7d0;">
                                <p style="margin:0 0 8px;font-weight:600;color:#065f46;"><?php echo sprintf(esc_html__('You are logged in as %s.', 'gd-client-portal'), esc_html($current_user->display_name)); ?></p>
                                <p style="margin:0;"><a class="button button-primary" href="<?php echo esc_url($dash); ?>" style="display:inline-block;padding:10px 14px;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;"><?php esc_html_e('Go to your dashboard', 'gd-client-portal'); ?></a></p>
                            </div>
                        <?php else : ?>
                        <?php wp_nonce_field('gd_client_portal_register', 'gd_client_portal_register_nonce'); ?>
                        <?php if (!empty($gd_post_purchase)) : ?>
                            <input type="hidden" name="gd_post_purchase" value="1" />
                            <?php if (!empty($gd_order_id)) : ?>
                                <input type="hidden" name="gd_order_id" value="<?php echo intval($gd_order_id); ?>" />
                            <?php endif; ?>
                        <?php endif; ?>

                        <div style="margin-bottom:14px;">
                            <label for="reg_first_name" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('First name', 'gd-client-portal'); ?> <span class="required" style="color:#dc2626;">*</span></label>
                            <input type="text" class="input-text" name="gd_first_name" id="reg_first_name" value="<?php echo esc_attr(wp_unslash(isset($_POST['gd_first_name']) ? $_POST['gd_first_name'] : '')); ?>" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <div style="margin-bottom:14px;">
                            <label for="reg_last_name" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('Last name', 'gd-client-portal'); ?> <span class="required" style="color:#dc2626;">*</span></label>
                            <input type="text" class="input-text" name="gd_last_name" id="reg_last_name" value="<?php echo esc_attr(wp_unslash(isset($_POST['gd_last_name']) ? $_POST['gd_last_name'] : '')); ?>" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <div style="margin-bottom:14px;">
                            <label for="reg_email" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('Email address', 'gd-client-portal'); ?> <span class="required" style="color:#dc2626;">*</span></label>
                            <input type="email" class="input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo esc_attr(wp_unslash(isset($_POST['email']) ? $_POST['email'] : '')); ?>" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <div style="margin-bottom:14px;">
                            <label for="reg_password" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('Password', 'gd-client-portal'); ?> <span class="required" style="color:#dc2626;">*</span></label>
                            <input type="password" class="input-text" name="password" id="reg_password" required style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <div style="margin-bottom:14px;">
                            <label for="reg_phone" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('Phone number', 'gd-client-portal'); ?></label>
                            <input type="text" class="input-text" name="gd_phone" id="reg_phone" value="<?php echo esc_attr(wp_unslash(isset($_POST['gd_phone']) ? $_POST['gd_phone'] : '')); ?>" style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <div style="margin-bottom:14px;">
                            <label for="reg_company" style="display:block;margin-bottom:8px;font-weight:700;color:#111827;"><?php esc_html_e('Company / organization', 'gd-client-portal'); ?></label>
                            <input type="text" class="input-text" name="gd_company" id="reg_company" value="<?php echo esc_attr(wp_unslash(isset($_POST['gd_company']) ? $_POST['gd_company'] : '')); ?>" style="display:block;width:100%;min-height:44px;padding:12px 14px;border:1px solid #cbd5e0;border-radius:10px;background:#fff;color:#111827;box-sizing:border-box;" />
                        </div>

                        <p class="form-row form-row-wide" style="margin:0;">
                            <button type="submit" class="button button-primary" name="gd_client_portal_register" value="1" style="display:block;width:100%;padding:12px 16px;border:none;border-radius:10px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;">
                                <?php esc_html_e('Create Account', 'gd-client-portal'); ?>
                            </button>
                        </p>
                        <?php endif; // is_user_logged_in ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        return $html ? $html : '<!-- Auth form failed to render -->';
    }
}

// Handle registration submissions
if (!function_exists('gd_client_portal_handle_registration')) {
    function gd_client_portal_handle_registration()
    {
        if ((defined('DOING_AJAX') && DOING_AJAX) || function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }

        if (empty($_POST['gd_client_portal_register'])) {
            return;
        }

        if (!isset($_POST['gd_client_portal_register_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_register_nonce']), 'gd_client_portal_register')) {
            $msg = rawurlencode(__('Invalid registration request.', 'gd-client-portal'));
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        // Rate limiting by IP
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $key = 'gd_reg_attempts_' . md5($ip);
        $attempts = (int) get_transient($key);
        $limit = 5;
        if ($attempts >= $limit) {
            $msg = rawurlencode(sprintf(__('Too many registration attempts. Please try again in %d minutes.', 'gd-client-portal'), 15));
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        // Optional Google reCAPTCHA v2/v3 verification if secret provided
        $recaptcha_secret = get_option('gd_client_portal_recaptcha_secret');
        if (!empty($recaptcha_secret)) {
            $token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            if (empty($token)) {
                $msg = rawurlencode(__('Please complete the CAPTCHA challenge.', 'gd-client-portal'));
                $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
                $sep = strpos($ref, '?') !== false ? '&' : '?';
                wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
                exit;
            }

            $resp = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array('secret' => $recaptcha_secret, 'response' => $token),
                'timeout' => 10,
            ));

            $success = false;
            if (!is_wp_error($resp)) {
                $body = wp_remote_retrieve_body($resp);
                $data = json_decode($body, true);
                if (!empty($data['success'])) {
                    $success = true;
                }
            }

            if (!$success) {
                $msg = rawurlencode(__('CAPTCHA verification failed.', 'gd-client-portal'));
                $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
                $sep = strpos($ref, '?') !== false ? '&' : '?';
                wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
                exit;
            }
        }

        $first_name = sanitize_text_field(wp_unslash($_POST['gd_first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['gd_last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        $phone = sanitize_text_field(wp_unslash($_POST['gd_phone'] ?? ''));
        $company = sanitize_text_field(wp_unslash($_POST['gd_company'] ?? ''));

        $errors = new WP_Error();

        if (empty($first_name)) {
            $errors->add('first_name', __('Please provide your first name.', 'gd-client-portal'));
        }

        if (empty($last_name)) {
            $errors->add('last_name', __('Please provide your last name.', 'gd-client-portal'));
        }

        if (!is_email($email)) {
            $errors->add('email', __('Please provide a valid email address.', 'gd-client-portal'));
        } elseif (email_exists($email)) {
            $errors->add('email_exists', __('An account with this email already exists.', 'gd-client-portal'));
        }

        if (empty($password) || strlen($password) < 6) {
            $errors->add('password', __('Please provide a password with at least 6 characters.', 'gd-client-portal'));
        }

        if ($errors->has_errors()) {
            // Redirect back with error messages
            $messages = implode(' ', $errors->get_error_messages());
            // Rate limit: increment failed attempts for IP
            $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
            $key = 'gd_reg_attempts_' . md5($ip);
            $attempts = (int) get_transient($key);
            $attempts++;
            set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);

            $msg = rawurlencode($messages);
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        // Create username from email prefix
        $username = sanitize_user(current(explode('@', $email)), true);
        $username_base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . $i;
            $i++;
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            $msg = rawurlencode(__('Unable to create user account.', 'gd-client-portal'));
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ));

        if (!empty($phone)) {
            update_user_meta($user_id, 'billing_phone', $phone);
        }

        if (!empty($company)) {
            update_user_meta($user_id, 'billing_company', $company);
            update_user_meta($user_id, 'gd_client_portal_company', $company);
        }

        // Auto-login new user
        wp_signon(array('user_login' => $username, 'user_password' => $password, 'remember' => true));

        // Redirect: if this was a post-purchase flow, prefer configured dashboard page
        $redirect = wp_get_referer();
        if (!empty($_POST['gd_post_purchase']) || !empty($_GET['gd_post_purchase'])) {
            $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
            if (!empty($dash)) {
                $redirect = $dash;
            }
        }

        if (!$redirect) {
            $redirect = home_url('/');
        }

        // Clear rate limit attempts on success
        if (!empty($key)) {
            delete_transient($key);
        }

        // Redirect with success message
        $msg = rawurlencode(__('Account created. You are now logged in.', 'gd-client-portal'));
        $sep = strpos($redirect, '?') !== false ? '&' : '?';
        wp_safe_redirect($redirect . $sep . 'gd_auth_success=' . $msg);
        exit;
    }
}

// Handle login submissions
if (!function_exists('gd_client_portal_handle_login')) {
    function gd_client_portal_handle_login()
    {
        if ((defined('DOING_AJAX') && DOING_AJAX) || function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }

        if (empty($_POST['gd_client_portal_login'])) {
            return;
        }

        if (!isset($_POST['gd_client_portal_login_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_login_nonce']), 'gd_client_portal_login')) {
            $msg = rawurlencode(__('Invalid login request.', 'gd-client-portal'));
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        $login = sanitize_text_field(wp_unslash($_POST['gd_login_user'] ?? ''));
        $password = wp_unslash($_POST['gd_login_pass'] ?? '');
        $remember = !empty($_POST['gd_login_remember']);

        if (empty($login) || empty($password)) {
            $msg = rawurlencode(__('Please provide your login credentials.', 'gd-client-portal'));
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        $creds = array(
            'user_login' => $login,
            'user_password' => $password,
            'remember' => $remember,
        );

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $msg = rawurlencode($user->get_error_message());
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $sep = strpos($ref, '?') !== false ? '&' : '?';
            wp_safe_redirect($ref . $sep . 'gd_auth_error=' . $msg);
            exit;
        }

        // Determine redirect: prefer dashboard and avoid sending user back to the auth page
        $redirect = wp_get_referer();
        $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
        $auth_page = gd_client_portal_get_redirect_page_url('gd_client_portal_auth_page_id', 'gd_client_portal_auth_page_url');

        // If this was a post-purchase flow, prefer the configured dashboard
        if (!empty($_POST['gd_post_purchase']) || !empty($_GET['gd_post_purchase'])) {
            if (!empty($dash)) {
                $redirect = $dash;
            }
        }

        // If the referer resolves to the auth page, avoid redirecting back to it — go to dashboard or home
        if (!empty($redirect) && !empty($auth_page)) {
            $redirect_path = wp_parse_url($redirect, PHP_URL_PATH) ?: $redirect;
            $auth_path = wp_parse_url($auth_page, PHP_URL_PATH) ?: $auth_page;
            if ($auth_path && strpos($redirect, $auth_path) !== false) {
                if (!empty($dash)) {
                    $redirect = $dash;
                } else {
                    $redirect = home_url('/');
                }
            }
        }

        if (empty($redirect)) {
            $redirect = !empty($dash) ? $dash : home_url('/');
        }

        wp_safe_redirect($redirect);
        exit;
    }
}

add_action('init', 'gd_client_portal_handle_registration');
add_action('init', 'gd_client_portal_handle_login');

if (!function_exists('gd_client_portal_register_auth_shortcode')) {
    function gd_client_portal_register_auth_shortcode()
    {
        if (!shortcode_exists('gd_client_portal_auth')) {
            add_shortcode('gd_client_portal_auth', 'gd_client_portal_render_auth_ui');
        }
    }
}

if (!function_exists('gd_client_portal_process_auth_shortcode_content')) {
    function gd_client_portal_process_auth_shortcode_content($content)
    {
        if (!is_string($content) || strpos($content, '[gd_client_portal_auth]') === false) {
            return $content;
        }

        return do_shortcode($content);
    }
}

add_action('init', 'gd_client_portal_register_auth_shortcode', 1);
add_filter('the_content', 'gd_client_portal_process_auth_shortcode_content', 11);

// AJAX endpoints for auth
if (!function_exists('gd_client_portal_ajax_login')) {
    function gd_client_portal_ajax_login()
    {
        // Only accept POST
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'])) {
            wp_send_json_error(array('message' => __('Invalid request method.', 'gd-client-portal')));
        }

        if (!isset($_POST['gd_client_portal_login_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_login_nonce']), 'gd_client_portal_login')) {
            wp_send_json_error(array('message' => __('Invalid login request.', 'gd-client-portal')));
        }

        $login = sanitize_text_field(wp_unslash($_POST['gd_login_user'] ?? ''));
        $password = wp_unslash($_POST['gd_login_pass'] ?? '');
        $remember = !empty($_POST['gd_login_remember']);

        if (empty($login) || empty($password)) {
            wp_send_json_error(array('message' => __('Please provide your login credentials.', 'gd-client-portal')));
        }

        $creds = array(
            'user_login' => $login,
            'user_password' => $password,
            'remember' => $remember,
        );

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            wp_send_json_error(array('message' => $user->get_error_message()));
        }

        // AJAX login redirect: if post-purchase intent was included, send to dashboard page
        $redirect = wp_get_referer();
        if (!empty($_POST['gd_post_purchase'])) {
            $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
            if (!empty($dash)) {
                $redirect = $dash;
            }
        }
        if (!$redirect) {
            $redirect = home_url('/');
        }

        wp_send_json_success(array('message' => __('Logged in successfully.', 'gd-client-portal'), 'redirect' => $redirect));
    }
}

if (!function_exists('gd_client_portal_ajax_register')) {
    function gd_client_portal_ajax_register()
    {
        if ('POST' !== strtoupper($_SERVER['REQUEST_METHOD'])) {
            wp_send_json_error(array('message' => __('Invalid request method.', 'gd-client-portal')));
        }

        if (!isset($_POST['gd_client_portal_register_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_register_nonce']), 'gd_client_portal_register')) {
            wp_send_json_error(array('message' => __('Invalid registration request.', 'gd-client-portal')));
        }

        // Rate limiting by IP
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'unknown';
        $key = 'gd_reg_attempts_' . md5($ip);
        $attempts = (int) get_transient($key);
        $limit = 5;
        if ($attempts >= $limit) {
            wp_send_json_error(array('message' => sprintf(__('Too many registration attempts. Please try again in %d minutes.', 'gd-client-portal'), 15)));
        }

        // Optional reCAPTCHA
        $recaptcha_secret = get_option('gd_client_portal_recaptcha_secret');
        if (!empty($recaptcha_secret)) {
            $token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            if (empty($token)) {
                // increment attempts
                $attempts++;
                set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
                wp_send_json_error(array('message' => __('Please complete the CAPTCHA challenge.', 'gd-client-portal')));
            }

            $resp = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array('secret' => $recaptcha_secret, 'response' => $token),
                'timeout' => 10,
            ));

            $success = false;
            if (!is_wp_error($resp)) {
                $body = wp_remote_retrieve_body($resp);
                $data = json_decode($body, true);
                if (!empty($data['success'])) {
                    $success = true;
                }
            }

            if (!$success) {
                $attempts++;
                set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
                wp_send_json_error(array('message' => __('CAPTCHA verification failed.', 'gd-client-portal')));
            }
        }

        $first_name = sanitize_text_field(wp_unslash($_POST['gd_first_name'] ?? ''));
        $last_name = sanitize_text_field(wp_unslash($_POST['gd_last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $password = wp_unslash($_POST['password'] ?? '');
        $phone = sanitize_text_field(wp_unslash($_POST['gd_phone'] ?? ''));
        $company = sanitize_text_field(wp_unslash($_POST['gd_company'] ?? ''));

        $errors = new WP_Error();

        if (empty($first_name)) {
            $errors->add('first_name', __('Please provide your first name.', 'gd-client-portal'));
        }

        if (empty($last_name)) {
            $errors->add('last_name', __('Please provide your last name.', 'gd-client-portal'));
        }

        if (!is_email($email)) {
            $errors->add('email', __('Please provide a valid email address.', 'gd-client-portal'));
        } elseif (email_exists($email)) {
            $errors->add('email_exists', __('An account with this email already exists.', 'gd-client-portal'));
        }

        if (empty($password) || strlen($password) < 6) {
            $errors->add('password', __('Please provide a password with at least 6 characters.', 'gd-client-portal'));
        }

        if ($errors->has_errors()) {
            $messages = implode(' ', $errors->get_error_messages());
            // increment attempts
            $attempts = (int) get_transient($key);
            $attempts++;
            set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
            wp_send_json_error(array('message' => $messages));
        }

        // Create username from email prefix
        $username = sanitize_user(current(explode('@', $email)), true);
        $username_base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . $i;
            $i++;
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            $attempts = (int) get_transient($key);
            $attempts++;
            set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
            $message = $user_id->get_error_message();
            if (empty($message)) {
                $message = __('Unable to create user account.', 'gd-client-portal');
            }
            wp_send_json_error(array('message' => $message));
        }

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ));

        if (!empty($phone)) {
            update_user_meta($user_id, 'billing_phone', $phone);
        }

        if (!empty($company)) {
            update_user_meta($user_id, 'billing_company', $company);
            update_user_meta($user_id, 'gd_client_portal_company', $company);
        }

        // Auto-login new user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        // clear attempts
        delete_transient($key);

        // AJAX register redirect: if post-purchase intent was included, send to dashboard page
        $redirect = wp_get_referer();
        if (!empty($_POST['gd_post_purchase'])) {
            $dash = gd_client_portal_get_redirect_page_url('gd_client_portal_dashboard_page_id', 'gd_client_portal_dashboard_page_url');
            if (!empty($dash)) {
                $redirect = $dash;
            }
        }
        if (!$redirect) {
            $redirect = home_url('/');
        }

        wp_send_json_success(array('message' => __('Account created. You are now logged in.', 'gd-client-portal'), 'redirect' => $redirect));
    }
}

add_action('wp_ajax_nopriv_gd_client_portal_ajax_login', 'gd_client_portal_ajax_login');
add_action('wp_ajax_gd_client_portal_ajax_login', 'gd_client_portal_ajax_login');
add_action('wp_ajax_nopriv_gd_client_portal_ajax_register', 'gd_client_portal_ajax_register');
add_action('wp_ajax_gd_client_portal_ajax_register', 'gd_client_portal_ajax_register');