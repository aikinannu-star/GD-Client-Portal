<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="gd-module-detail gd-invoice-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Billing', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Invoices', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html__('1 payment due', 'gd-client-portal'); ?></span>
    </div>

    <div class="gd-invoice-stack">
        <article class="gd-invoice-item">
            <div class="gd-invoice-head">
                <strong><?php echo esc_html__('INV-1048', 'gd-client-portal'); ?></strong>
                <span><?php echo esc_html__('Due 12 Aug', 'gd-client-portal'); ?></span>
            </div>
            <p><?php echo esc_html__('Design retainer and project coordination services.', 'gd-client-portal'); ?></p>
            <div class="gd-invoice-amount"><?php echo esc_html__('USD $2,400.00', 'gd-client-portal'); ?></div>
        </article>
    </div>
</div>
