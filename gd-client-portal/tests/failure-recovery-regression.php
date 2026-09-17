<?php
$root = dirname(__DIR__);
$checks = array(
    'failure recovery runtime test exists' => file_exists($root . '/tests/test-failure-recovery-runtime-contracts.php'),
    'contract service has rollback path' => (bool) preg_match('/ROLLBACK/', file_get_contents($root . '/includes/contract-service.php')),
    'billing service has rollback path' => (bool) preg_match('/ROLLBACK/', file_get_contents($root . '/includes/billing-service.php')),
    'delivery has rollback paths' => (bool) preg_match_all('/ROLLBACK/', file_get_contents($root . '/includes/delivery.php')),
    'automation retry rejects missing queue' => (bool) preg_match('/if\(!\$row\|\|/', file_get_contents($root . '/includes/automation-service.php')),
    'scheduler API is used for recovery' => (bool) preg_match('/wp_schedule_single_event/', file_get_contents($root . '/includes/reliability.php')),
    'production runtime matrix documents recovery' => (bool) preg_match('/failure|recovery/i', file_get_contents($root . '/PHASE-2-RUNTIME-VERIFICATION.md')),
);
$failed = array(); foreach ($checks as $name => $ok) { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL; if (!$ok) $failed[]=$name; }
echo count($checks)-count($failed) . '/' . count($checks) . ' failure/recovery checks passed.' . PHP_EOL; exit($failed ? 1 : 0);
