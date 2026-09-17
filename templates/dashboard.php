<?php
/**
 * Dashboard template for the GD Client Portal plugin.
 */
?>
<div class="gd-client-portal-dashboard">
    <h2><?php echo esc_html($title); ?></h2>
    <p><?php echo esc_html($welcome_message); ?></p>

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
