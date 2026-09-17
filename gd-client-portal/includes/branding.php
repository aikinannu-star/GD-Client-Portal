<?php
/**
 * Tenant-specific portal branding and personalization.
 */
if (!defined('ABSPATH')) exit;

function gd_client_portal_sanitize_branding_color($value, $fallback) {
    $value = sanitize_hex_color($value);
    return $value ?: $fallback;
}

function gd_client_portal_save_branding() {
    if (empty($_POST['gd_client_portal_save_branding'])) return;
    if (!current_user_can('gd_client_portal_access_admin')) {
        wp_die(esc_html__('You do not have permission to manage portal branding.', 'gd-client-portal'));
    }
    if (empty($_POST['gd_client_portal_branding_nonce']) || !wp_verify_nonce(wp_unslash($_POST['gd_client_portal_branding_nonce']), 'gd_client_portal_save_branding')) {
        wp_die(esc_html__('Security check failed.', 'gd-client-portal'));
    }

    $tenant_id = gd_client_portal_get_branding_tenant_id();
    if ($tenant_id <= 0) {
        wp_die(esc_html__('Select a tenant before saving tenant branding.', 'gd-client-portal'));
    }
    if (!gd_client_portal_is_platform_admin() && $tenant_id !== gd_client_portal_get_current_tenant_id()) {
        wp_die(esc_html__('You cannot modify another tenant.', 'gd-client-portal'));
    }

    $tenants = gd_client_portal_get_all_tenants();
    if (empty($tenants[$tenant_id])) {
        wp_die(esc_html__('Tenant not found.', 'gd-client-portal'));
    }

    $tenants[$tenant_id]['logo_url'] = esc_url_raw(wp_unslash($_POST['logo_url'] ?? ''));
    $tenants[$tenant_id]['primary_color'] = gd_client_portal_sanitize_branding_color(wp_unslash($_POST['primary_color'] ?? ''), '#2563eb');
    $tenants[$tenant_id]['accent_color'] = gd_client_portal_sanitize_branding_color(wp_unslash($_POST['accent_color'] ?? ''), '#0f172a');
    $tenants[$tenant_id]['portal_title'] = sanitize_text_field(wp_unslash($_POST['portal_title'] ?? ''));
    $tenants[$tenant_id]['portal_welcome'] = sanitize_textarea_field(wp_unslash($_POST['portal_welcome'] ?? ''));
    $tenants[$tenant_id]['button_label'] = sanitize_text_field(wp_unslash($_POST['button_label'] ?? ''));
    update_option('gd_client_portal_tenants', array_values($tenants));

    wp_safe_redirect(add_query_arg(array('page'=>'gd-client-portal-branding','tenant_id'=>$tenant_id,'branding-updated'=>'1'), admin_url('admin.php')));
    exit;
}
add_action('admin_init', 'gd_client_portal_save_branding', 5);

function gd_client_portal_render_branding_page() {
    if (!current_user_can('gd_client_portal_access_admin')) wp_die(esc_html__('You do not have permission to access this page.', 'gd-client-portal'));
    $tenant_id = gd_client_portal_get_branding_tenant_id();
    if ($tenant_id <= 0) {
        if (!gd_client_portal_is_platform_admin()) return;
        $tenants = gd_client_portal_get_all_tenants();
        $tenant_id = (int) (array_key_first($tenants) ?: 0);
    }
    if (!gd_client_portal_is_platform_admin() && $tenant_id !== gd_client_portal_get_current_tenant_id()) $tenant_id = gd_client_portal_get_current_tenant_id();
    $tenants = gd_client_portal_get_all_tenants();
    if ($tenant_id <= 0 || empty($tenants[$tenant_id])) {
        echo '<div class="wrap"><h1>'.esc_html__('Portal Branding','gd-client-portal').'</h1><div class="notice notice-warning"><p>'.esc_html__('Create or assign a tenant before configuring branding.','gd-client-portal').'</p></div></div>';
        return;
    }
    $tenant = $tenants[$tenant_id];
    ?>
    <div class="wrap">
      <h1><?php echo esc_html__('Portal Branding & Personalization','gd-client-portal'); ?></h1>
      <p><?php echo esc_html__('Give each tenant its own branded client workspace without changing the shared portal architecture.','gd-client-portal'); ?></p>
      <?php if (!empty($_GET['branding-updated'])): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Branding saved successfully.','gd-client-portal'); ?></p></div><?php endif; ?>
      <?php if (gd_client_portal_is_platform_admin()): ?>
        <form method="get" style="margin:18px 0;"><input type="hidden" name="page" value="gd-client-portal-branding"><label><strong><?php echo esc_html__('Tenant','gd-client-portal'); ?></strong>
          <select name="tenant_id" onchange="this.form.submit()">
          <?php foreach ($tenants as $t): ?><option value="<?php echo absint($t['id']); ?>" <?php selected($tenant_id,$t['id']); ?>><?php echo esc_html($t['name']); ?></option><?php endforeach; ?></select>
        </form>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=gd-client-portal-branding&tenant_id='.$tenant_id)); ?>">
        <?php wp_nonce_field('gd_client_portal_save_branding','gd_client_portal_branding_nonce'); ?><input type="hidden" name="gd_client_portal_save_branding" value="1"><input type="hidden" name="tenant_id" value="<?php echo absint($tenant_id); ?>">
        <table class="form-table" role="presentation">
          <tr><th><label for="gd-brand-logo">Logo URL</label></th><td><input class="regular-text" id="gd-brand-logo" name="logo_url" type="url" value="<?php echo esc_attr($tenant['logo_url']); ?>"><p class="description">Use a WordPress Media Library image URL.</p></td></tr>
          <tr><th><label for="gd-brand-primary">Primary color</label></th><td><input id="gd-brand-primary" name="primary_color" type="color" value="<?php echo esc_attr($tenant['primary_color']); ?>"> <code><?php echo esc_html($tenant['primary_color']); ?></code></td></tr>
          <tr><th><label for="gd-brand-accent">Accent color</label></th><td><input id="gd-brand-accent" name="accent_color" type="color" value="<?php echo esc_attr($tenant['accent_color']); ?>"> <code><?php echo esc_html($tenant['accent_color']); ?></code></td></tr>
          <tr><th><label for="gd-brand-title">Portal title</label></th><td><input class="regular-text" id="gd-brand-title" name="portal_title" value="<?php echo esc_attr($tenant['portal_title']); ?>" placeholder="<?php echo esc_attr($tenant['name'].' Client Portal'); ?>"></td></tr>
          <tr><th><label for="gd-brand-welcome">Welcome message</label></th><td><textarea class="large-text" rows="3" id="gd-brand-welcome" name="portal_welcome"><?php echo esc_textarea($tenant['portal_welcome']); ?></textarea></td></tr>
          <tr><th><label for="gd-brand-button">Primary button label</label></th><td><input class="regular-text" id="gd-brand-button" name="button_label" value="<?php echo esc_attr($tenant['button_label']); ?>" placeholder="Open Workspace"></td></tr>
        </table>
        <?php submit_button(__('Save Tenant Branding','gd-client-portal')); ?>
      </form>
      <div style="max-width:980px;padding:22px;margin-top:20px;border:1px solid #dcdcde;background:#fff;border-radius:12px;">
        <h2><?php echo esc_html__('Brand preview','gd-client-portal'); ?></h2>
        <div style="border-radius:14px;padding:24px;color:#fff;background:linear-gradient(135deg, <?php echo esc_attr($tenant['accent_color']); ?>, <?php echo esc_attr($tenant['primary_color']); ?>);">
          <?php if ($tenant['logo_url']): ?><img src="<?php echo esc_url($tenant['logo_url']); ?>" alt="" style="max-height:54px;max-width:180px;background:#fff;border-radius:8px;padding:6px;margin-bottom:14px;"><?php endif; ?>
          <h2 style="color:#fff;margin:0 0 8px;"><?php echo esc_html($tenant['portal_title'] ?: $tenant['name'].' Client Portal'); ?></h2>
          <p style="margin:0 0 18px;opacity:.92;"><?php echo esc_html($tenant['portal_welcome'] ?: 'Welcome to your client workspace.'); ?></p>
          <span style="display:inline-block;background:#fff;color:<?php echo esc_attr($tenant['accent_color']); ?>;padding:9px 14px;border-radius:8px;font-weight:600;"><?php echo esc_html($tenant['button_label'] ?: 'Open Workspace'); ?></span>
        </div>
      </div>
    </div>
    <?php
}

function gd_client_portal_register_branding_menu() {
    add_submenu_page('gd-client-portal', __('Branding & Personalization','gd-client-portal'), __('Branding & Personalization','gd-client-portal'), 'gd_client_portal_access_admin', 'gd-client-portal-branding', 'gd_client_portal_render_branding_page');
}
add_action('admin_menu','gd_client_portal_register_branding_menu',20);

function gd_client_portal_branding_admin_bar_class($classes) {
    if (is_user_logged_in() && gd_client_portal_get_current_tenant_id() > 0) $classes[]='gdcp-tenant-branded';
    return $classes;
}
add_filter('admin_body_class','gd_client_portal_branding_admin_bar_class');
