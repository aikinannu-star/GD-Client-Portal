<?php
/** Static regression contract for external integration concurrency/idempotency. */
$root = dirname(__DIR__);
$woo = file_get_contents($root.'/includes/woo-automation.php');
$payments = file_get_contents($root.'/includes/payments.php');
$service = file_get_contents($root.'/includes/payment-service.php');
$checks = array(
 'Guest claim uses per-email lock' => strpos($woo, 'GET_LOCK') !== false && strpos($woo, 'gdcp_woo_guest_claim_') !== false,
 'Guest claim update is conditional' => strpos($woo, 'AND user_id=0') !== false,
 'Guest claim side effect only after successful update' => strpos($woo, 'if ($updated === 1) do_action') !== false,
 'Paystack webhook rejects over-balance replay' => strpos($payments, 'if($received>0 && $received<=$balance+0.0001)') !== false,
 'Payment service uses transaction' => strpos($service, "START TRANSACTION") !== false && strpos($service, "ROLLBACK") !== false && strpos($service, "COMMIT") !== false,
 'Payment reference idempotency remains enforced' => strpos($service, 'find_by_reference($reference,true)') !== false && strpos($service, 'payment_reference_conflict') !== false,
);
$failed=array(); foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - $name\n"; if(!$ok)$failed[]=$name;} exit($failed?1:0);
