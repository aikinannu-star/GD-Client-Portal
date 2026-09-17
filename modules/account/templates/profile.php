<?php
/**
 * Simple account profile template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gd-account-profile">
    <form method="post" enctype="multipart/form-data" class="gd-account-form">
        <?php wp_nonce_field('gd_client_portal_update_account', 'gd_client_portal_update_account_nonce'); ?>
        <input type="hidden" name="gd_client_portal_update_account" value="1" />

        <div class="gd-account-grid">
            <div class="gd-account-card gd-account-main">
                <h3><?php echo esc_html(sprintf(__("%s's Profile", 'gd-client-portal'), $user->display_name)); ?></h3>

                <?php if (!empty($_GET['gd_client_portal_account_updated'])) : ?>
                    <div class="updated notice"><p><?php echo esc_html__('Profile updated.', 'gd-client-portal'); ?></p></div>
                <?php endif; ?>

                <?php
                $billing_email = get_user_meta($user->ID, 'billing_email', true);
                $billing_phone = get_user_meta($user->ID, 'billing_phone', true);
                $company_name = get_user_meta($user->ID, 'company_name', true);
                $company_address = get_user_meta($user->ID, 'company_address', true);
                $contact_person = get_user_meta($user->ID, 'contact_person', true);
                $preferred_contact_method = get_user_meta($user->ID, 'preferred_contact_method', true);
                $account_notes = get_user_meta($user->ID, 'account_notes', true);
                $avatar_id = get_user_meta($user->ID, 'gd_client_portal_avatar_id', true);
                $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
                ?>

                <div class="gd-account-avatar" data-initial="<?php echo esc_attr(substr($user->display_name, 0, 1)); ?>">
                    <?php if ($avatar_url) : ?>
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" width="96" height="96" />
                    <?php else : ?>
                        <div class="gd-avatar-placeholder"><?php echo esc_html(substr($user->display_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    <div class="gd-account-avatar-meta">
                        <label class="gd-field gd-field-inline">
                            <span><?php echo esc_html__('Upload avatar', 'gd-client-portal'); ?></span>
                            <input type="file" name="gd_client_portal_avatar" accept="image/*" />
                        </label>
                        <div class="gd-avatar-controls">
                            <button type="button" class="gd-btn gd-btn-alt" id="gd-avatar-remove"><?php echo esc_html__('Remove avatar', 'gd-client-portal'); ?></button>
                        </div>
                    </div>
                    <div class="gd-account-notice" id="gd-account-notice" aria-live="polite"></div>
                </div>

                <div class="gd-account-meta">
                    <label class="gd-field"><span><?php echo esc_html__('Display name', 'gd-client-portal'); ?></span>
                        <input type="text" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" />
                    </label>
                    <div class="gd-name-row">
                        <label class="gd-field"><span><?php echo esc_html__('First name', 'gd-client-portal'); ?></span>
                            <input type="text" name="first_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'first_name', true)); ?>" />
                        </label>
                        <label class="gd-field"><span><?php echo esc_html__('Last name', 'gd-client-portal'); ?></span>
                            <input type="text" name="last_name" value="<?php echo esc_attr(get_user_meta($user->ID, 'last_name', true)); ?>" />
                        </label>
                    </div>
                </div>

                <div class="gd-account-card gd-account-billing">
                    <h4><?php echo esc_html__('Billing & Company', 'gd-client-portal'); ?></h4>
                    <label class="gd-field"><span><?php echo esc_html__('Billing email', 'gd-client-portal'); ?></span>
                        <input type="email" name="billing_email" value="<?php echo esc_attr($billing_email ?: $user->user_email); ?>" />
                    </label>
                    <label class="gd-field"><span><?php echo esc_html__('Billing phone', 'gd-client-portal'); ?></span>
                        <input type="text" name="billing_phone" value="<?php echo esc_attr($billing_phone); ?>" />
                    </label>
                    <label class="gd-field"><span><?php echo esc_html__('Company name', 'gd-client-portal'); ?></span>
                        <input type="text" name="company_name" value="<?php echo esc_attr($company_name); ?>" />
                    </label>
                    <label class="gd-field"><span><?php echo esc_html__('Company address', 'gd-client-portal'); ?></span>
                        <textarea name="company_address"><?php echo esc_textarea($company_address); ?></textarea>
                    </label>
                </div>

            </div>

            <div class="gd-account-card gd-account-custom">
                <h4><?php echo esc_html__('Custom Fields', 'gd-client-portal'); ?></h4>
                <?php
                $custom_keys = apply_filters('gd_client_portal_account_custom_fields', array('contact_person', 'preferred_contact_method', 'account_notes'));
                foreach ($custom_keys as $key) {
                    $val = get_user_meta($user->ID, $key, true);
                    if ($key === 'preferred_contact_method') {
                        ?>
                        <label class="gd-field"><span><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></span>
                            <select name="<?php echo esc_attr($key); ?>">
                                <option value="" <?php selected('', $val); ?>><?php echo esc_html__('—', 'gd-client-portal'); ?></option>
                                <option value="email" <?php selected('email', $val); ?>><?php echo esc_html__('Email', 'gd-client-portal'); ?></option>
                                <option value="phone" <?php selected('phone', $val); ?>><?php echo esc_html__('Phone', 'gd-client-portal'); ?></option>
                            </select>
                        </label>
                        <?php
                    } elseif ($key === 'account_notes') {
                        ?>
                        <label class="gd-field"><span><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></span>
                            <textarea name="<?php echo esc_attr($key); ?>"><?php echo esc_textarea($val); ?></textarea>
                        </label>
                        <?php
                    } else {
                        ?>
                        <label class="gd-field"><span><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></span>
                            <input type="text" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>" />
                        </label>
                        <?php
                    }
                }
                ?>
            </div>

        </div>

        <div class="gd-account-actions">
            <button class="gd-btn" type="submit"><?php echo esc_html__('Save profile', 'gd-client-portal'); ?></button>
            <a class="gd-btn gd-btn-alt" href="<?php echo esc_url(wp_logout_url(home_url())); ?>"><?php echo esc_html__('Log out', 'gd-client-portal'); ?></a>
        </div>
    </form>
</div>
