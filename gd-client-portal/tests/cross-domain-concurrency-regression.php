<?php
/**
 * Cross-domain concurrency/idempotency contract checks for GD Client Portal.
 * Run with: php tests/cross-domain-concurrency-regression.php
 */
$root=dirname(__DIR__);
$payment=file_get_contents($root.'/includes/payment-service.php');
$contracts=file_get_contents($root.'/includes/contract-service.php');
$billing=file_get_contents($root.'/includes/billing-service.php');
$billing_repo=file_get_contents($root.'/includes/billing-repository.php');
$approval=file_get_contents($root.'/app/Services/ApprovalService.php');
$delivery=file_get_contents($root.'/includes/delivery.php');

$checks=array(
    'payment reference uniqueness is schema-backed' => strpos(file_get_contents($root.'/includes/payments.php'),'UNIQUE KEY reference(reference)')!==false,
    'payment reference is invoice-bound on replay' => strpos($payment,"payment_reference_conflict")!==false && strpos($payment,'$existing->invoice_id)!==$invoice_id')!==false,
    'payment amount is checked on idempotent replay' => strpos($payment,'payment_amount_conflict')!==false && strpos($payment,'$existing_amount-$amount')!==false,
    'payment insert race is recovered as idempotent when exact match exists' => strpos($payment,'$concurrent=$this->payments->find_by_reference')!==false,
    'contract creation locks the accepted quote' => strpos($contracts,'get_quote_for_access(absint($quote_id),true)')!==false && strpos($billing_repo,'FOR UPDATE')!==false,
    'contract creation locks existing quote contract before returning' => strpos($contracts,'latest_for_quote($q->id,true)')!==false,
    'approval submission locks project row' => strpos($approval,'WHERE id=%d FOR UPDATE')!==false,
    'approval decision locks active approval' => strpos($approval,'find_active_for_project($project_id,$project->tenant_id,true)')!==false,
    'delivery publication locks project row' => strpos($delivery,'SELECT * FROM {$project_table} WHERE id=%d FOR UPDATE')!==false,
    'delivery finalization locks project row' => substr_count($delivery,'SELECT * FROM {$project_table} WHERE id=%d FOR UPDATE')>=2,
);
$failed=array();
foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." — $name\n"; if(!$ok)$failed[]=$name; }
if($failed){ fwrite(STDERR,"\n".count($failed)." cross-domain concurrency contract(s) failed.\n"); exit(1); }
echo "\nCross-domain concurrency/idempotency contract passed.\n";
