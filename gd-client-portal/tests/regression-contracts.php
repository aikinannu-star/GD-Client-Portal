
// v7.6.0 business-object integrity contracts.
gdcp_contract('billing quote creation validates project tenant/client consistency', function($source){ return strpos($source, "Project does not match client/tenant.") !== false; }, __FILE__);
gdcp_contract('document upload validates supplied contract against project/client', function($source){ return strpos($source, "Contract does not match project/client.") !== false; }, __FILE__);
gdcp_contract('Paystack verification validates metadata against invoice', function($source){ return strpos($source, "Payment metadata does not match the requested invoice.") !== false && strpos($source, "Payment tenant does not match the invoice.") !== false; }, __FILE__);
gdcp_contract('Payment recording locks invoice and prevents concurrent overpayment', function($source){ return strpos($source, "START TRANSACTION") !== false && strpos($source, "FOR UPDATE") !== false && strpos($source, "UPDATE $it SET amount_paid") !== false; }, __FILE__);
gdcp_contract('Paystack verification binds reference and currency', function($source){ return strpos($source, 'Verified payment reference does not match the requested reference.') !== false && strpos($source, 'Payment currency does not match the invoice.') !== false; }, __FILE__);
gdcp_contract('Paystack webhook rejects over-balance payments without tolerance', function($source){ return strpos($source, 'if($received<=gd_client_portal_payment_balance($invoice))') !== false; }, __FILE__);

// Approval state-machine integrity: approval versions must bind to the current deliverable
// version, and concurrent creation/decision paths must lock the project/approval row.
gdcp_regression_contract('approval_version_tracks_delivery_version', function(){
    $src = file_get_contents(GDCP_PLUGIN_PATH . 'includes/approvals.php');
    return strpos($src, 'gd_client_portal_get_latest_delivery($project_id, false)') !== false
        && strpos($src, 'if ($delivery) $version = absint($delivery->version);') !== false;
});
gdcp_regression_contract('approval_creation_serializes_project', function(){
    $src = file_get_contents(GDCP_PLUGIN_PATH . 'includes/approvals.php');
    return strpos($src, 'FOR UPDATE') !== false && strpos($src, "START TRANSACTION") !== false && strpos($src, "COMMIT") !== false;
});
gdcp_regression_contract('approval_decision_is_pending_state_guarded', function(){
    $src = file_get_contents(GDCP_PLUGIN_PATH . 'includes/approvals.php');
    return strpos($src, "AND status='pending'") !== false && strpos($src, 'tenant_id=%d') !== false;
});

gdcp_regression_contract('quote to invoice conversion is serialized and relation checked', function(){
    $src = file_get_contents(GDCP_PLUGIN_PATH . 'includes/payments.php');
    return strpos($src, "SELECT * FROM '.$qt.' WHERE id=%d FOR UPDATE") !== false
        && strpos($src, 'Quote project does not match its tenant/client.') !== false
        && strpos($src, "START TRANSACTION") !== false
        && strpos($src, "COMMIT") !== false;
});

// Delivery maturity contracts: version allocation/finalization must be serialized per project.
$delivery_source = file_get_contents(dirname(__DIR__) . '/includes/delivery.php');
gdcp_assert_contract(
    strpos($delivery_source, "SELECT * FROM {$project_table} WHERE id=%d FOR UPDATE") !== false || strpos($delivery_source, 'WHERE id=%d FOR UPDATE') !== false,
    'Delivery publication/finalization locks the project row before version/state mutation.'
);
gdcp_assert_contract(
    strpos($delivery_source, "START TRANSACTION") !== false && strpos($delivery_source, "COMMIT") !== false,
    'Delivery publication/finalization uses transactional state changes.'
);
gdcp_assert_contract(
    strpos($delivery_source, "status IN ('published','approved')") !== false,
    'Finalization resolves the current published/approved delivery inside the transaction.'
);
