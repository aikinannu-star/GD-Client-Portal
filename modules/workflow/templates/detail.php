<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="gd-module-detail gd-workflow-detail">
    <div class="gd-module-summary-row gd-workflow-summary">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Project delivery', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Workflow Overview', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill gd-pill-success"><?php echo esc_html__('On track', 'gd-client-portal'); ?></span>
    </div>

    <div class="gd-workflow-grid">
        <div class="gd-workflow-stage is-active">
            <span class="gd-workflow-number"><?php echo esc_html__('01', 'gd-client-portal'); ?></span>
            <strong><?php echo esc_html__('Discovery', 'gd-client-portal'); ?></strong>
            <small><?php echo esc_html__('Completed', 'gd-client-portal'); ?></small>
        </div>
        <div class="gd-workflow-stage is-active">
            <span class="gd-workflow-number"><?php echo esc_html__('02', 'gd-client-portal'); ?></span>
            <strong><?php echo esc_html__('Design', 'gd-client-portal'); ?></strong>
            <small><?php echo esc_html__('Approved', 'gd-client-portal'); ?></small>
        </div>
        <div class="gd-workflow-stage is-current">
            <span class="gd-workflow-number"><?php echo esc_html__('03', 'gd-client-portal'); ?></span>
            <strong><?php echo esc_html__('Build', 'gd-client-portal'); ?></strong>
            <small><?php echo esc_html__('In progress', 'gd-client-portal'); ?></small>
        </div>
        <div class="gd-workflow-stage">
            <span class="gd-workflow-number"><?php echo esc_html__('04', 'gd-client-portal'); ?></span>
            <strong><?php echo esc_html__('Review', 'gd-client-portal'); ?></strong>
            <small><?php echo esc_html__('Pending', 'gd-client-portal'); ?></small>
        </div>
    </div>
</div>
