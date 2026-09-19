<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$gd_invoices = function_exists('gd_client_portal_billing_get_invoices') ? gd_client_portal_billing_get_invoices(100) : array();
$gd_invoices = is_array($gd_invoices) ? $gd_invoices : array();
$gd_outstanding = array();
foreach ($gd_invoices as $gd_invoice) {
    $gd_status = isset($gd_invoice->status) ? sanitize_key($gd_invoice->status) : '';
    $gd_balance = max(0, (float) ($gd_invoice->total ?? 0) - (float) ($gd_invoice->amount_paid ?? 0));
    if ($gd_balance > 0 && !in_array($gd_status, array('cancelled', 'canceled'), true)) {
        $gd_outstanding[] = $gd_invoice;
    }
}
?>
<div class="gd-module-detail gd-invoice-detail">
    <div class="gd-module-summary-row">
        <div>
            <p class="gd-module-eyebrow"><?php echo esc_html__('Billing', 'gd-client-portal'); ?></p>
            <h2><?php echo esc_html__('Invoices', 'gd-client-portal'); ?></h2>
        </div>
        <span class="gd-pill"><?php echo esc_html(sprintf(_n('%d outstanding', '%d outstanding', count($gd_outstanding), 'gd-client-portal'), count($gd_outstanding))); ?></span>
    </div>

    <?php if (empty($gd_invoices)) : ?>
        <div class="gd-module-empty-state"><p><?php echo esc_html__('No invoices are available for your account yet.', 'gd-client-portal'); ?></p></div>
    <?php else : ?>
        <div class="gd-invoice-stack">
            <?php foreach (array_slice($gd_invoices, 0, 6) as $gd_invoice) :
                $gd_balance = max(0, (float) ($gd_invoice->total ?? 0) - (float) ($gd_invoice->amount_paid ?? 0));
                $gd_due = !empty($gd_invoice->due_date) ? strtotime($gd_invoice->due_date) : 0;
                $gd_currency = !empty($gd_invoice->currency) ? $gd_invoice->currency : 'GHS';
            ?>
                <article class="gd-invoice-item">
                    <div class="gd-invoice-head">
                        <strong><?php echo esc_html($gd_invoice->invoice_number ?? ('#' . absint($gd_invoice->id ?? 0))); ?></strong>
                        <span><?php echo esc_html($gd_due ? sprintf(__('Due %s', 'gd-client-portal'), wp_date(get_option('date_format'), $gd_due)) : ucfirst((string) ($gd_invoice->status ?? '')); ?></span>
                    </div>
                    <p><?php echo esc_html($gd_invoice->description ?? __('Invoice for client services.', 'gd-client-portal')); ?></p>
                    <div class="gd-invoice-amount"><?php echo esc_html(function_exists('gd_client_portal_billing_money') ? gd_client_portal_billing_money($gd_balance > 0 ? $gd_balance : (float) ($gd_invoice->total ?? 0), $gd_currency) : number_format_i18n((float) ($gd_balance > 0 ? $gd_balance : $gd_invoice->total), 2) . ' ' . $gd_currency); ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
