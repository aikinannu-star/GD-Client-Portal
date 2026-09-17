<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="gd-module-detail gd-deliverables-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Milestones', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Deliverables', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill gd-pill-success"><?php echo esc_html__('Ready', 'gd-client-portal'); ?></span>
    </div>

    <div class="gd-deliverable-stack">
        <article class="gd-deliverable-item">
            <div class="gd-deliverable-head">
                <strong><?php echo esc_html__('Brand direction deck', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Approved', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('Creative rationale and design narrative for the revised visual direction.', 'gd-client-portal'); ?></p>
        </article>
        <article class="gd-deliverable-item">
            <div class="gd-deliverable-head">
                <strong><?php echo esc_html__('Homepage prototype', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Review', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('Interactive homepage concept with the full content architecture and conversion flow.', 'gd-client-portal'); ?></p>
        </article>
    </div>
</div>
