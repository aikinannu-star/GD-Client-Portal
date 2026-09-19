<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$invoices = array();
if (function_exists('gdcp_billing_service') && function_exists('gd_client_portal_get_current_tenant_id')) {
    $repo = new GDCP_Billing_Repository();
    $invoices = $repo->list_outstanding_for_scope(get_current_user_id(), gd_client_portal_get_current_tenant_id(), function_exists('gd_client_portal_user_is_tenant_admin') && gd_client_portal_user_is_tenant_admin(), function_exists('gd_client_portal_is_platform_admin') && gd_client_portal_is_platform_admin(), 20);
}
?>
<div class="gd-module-detail gd-invoice-detail">
    <div class="gd-module-summary-row">
        <div><p class="gd-module-eyebrow"><?php esc_html_e('Billing', 'gd-client-portal'); ?></p><h2><?php esc_html_e('Invoices', 'gd-client-portal'); ?></h2></div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d outstanding', '%d outstanding', count($invoices), 'gd-client-portal'), count($invoices))); ?></span>
    </div>
    <?php if (empty($invoices)) : ?>
        <div class="gd-empty-state"><strong><?php esc_html_e('No outstanding invoices', 'gd-client-portal'); ?></strong><p><?php esc_html_e('Your billing is up to date or there are no invoices available for this account.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-invoice-stack">
        <?php foreach ($invoices as $invoice) : ?>
            <article class="gd-invoice-item">
                <div class="gd-invoice-head"><strong><?php echo esc_html($invoice->invoice_number ?? ('#'.absint($invoice->id))); ?></strong><span><?php echo esc_html($invoice->due_date ?? ''); ?></span></div>
                <p><?php echo esc_html($invoice->description ?? __('Invoice', 'gd-client-portal')); ?></p>
                <div class="gd-invoice-amount"><?php echo esc_html(($invoice->currency ?? 'GHS') . ' ' . number_format_i18n((float)($invoice->total ?? 0), 2)); ?></div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
