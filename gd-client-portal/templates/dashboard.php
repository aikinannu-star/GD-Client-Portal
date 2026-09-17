<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
/**
 * Dashboard template for the GD Client Portal plugin.
 */
?>
<div class="gd-client-portal-dashboard gdcp-branded" style="--gdcp-primary:<?php echo esc_attr($branding['primary_color'] ?? '#2563eb'); ?>;--gdcp-accent:<?php echo esc_attr($branding['accent_color'] ?? '#0f172a'); ?>;">
    <?php if (!empty($branding['name']) || !empty($branding['logo_url'])) : ?>
        <div class="gdcp-brand-header">
            <?php if (!empty($branding['logo_url'])) : ?><img class="gdcp-brand-header__logo" src="<?php echo esc_url($branding['logo_url']); ?>" alt="<?php echo esc_attr($branding['name'] ?? ''); ?>"><?php endif; ?>
            <div><h2 class="gdcp-brand-header__title"><?php echo esc_html($title); ?></h2><p class="gdcp-brand-header__welcome"><?php echo esc_html($welcome_message); ?></p></div>
        </div>
    <?php else : ?>
        <h2><?php echo esc_html($title); ?></h2><p><?php echo esc_html($welcome_message); ?></p>
    <?php endif; ?>

    <?php if (!empty($module_title)) : ?>
        <div class="gd-module-header">
            <h3><?php echo esc_html($module_title); ?></h3>
            <?php if (!empty($module_description)) : ?><p class="gd-module-desc"><?php echo esc_html($module_description); ?></p><?php endif; ?>
            <p><a class="gd-btn gd-btn-alt" href="<?php echo esc_url($back_url); ?>"><?php echo esc_html__('Back to dashboard', 'gd-client-portal'); ?></a></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($content)) : ?>
        <?php echo wp_kses_post($content); ?>
    <?php endif; ?>
</div>
