<?php
/**
 * Phase 2 runtime matrix contract.
 *
 * Framework-independent preflight: verifies that every required runtime
 * scenario has a concrete implementation/test surface before the disposable
 * WordPress + MySQL integration suite is executed.
 */

$root = dirname(__DIR__);
$checks = array();

$requirements = array(
    'tenant_isolation' => array('files' => array('app/Repositories/TenantRepository.php'), 'needles' => array('user_tenant(')),
    'project_authorization' => array('files' => array('app/Services/ProjectService.php'), 'needles' => array('gdcp_can(')),
    'ajax_security' => array('files' => array('includes/security.php'), 'needles' => array('gd_client_portal_ajax_guard')),
    'protected_files' => array('files' => array('includes/security.php'), 'needles' => array('gd_client_portal_stream_private_file')),
    'workflow_transitions' => array('files' => array('app/Services/ProjectService.php'), 'needles' => array('transition_stage(')),
    'contracts_approvals_delivery' => array('files' => array('includes/delivery.php'), 'needles' => array('gdcp_approval_service')),
    'payments' => array('files' => array('includes/payment-repository.php'), 'needles' => array('find_by_reference(')),
    'notifications' => array('files' => array('includes/notification-repository.php'), 'needles' => array('unread_count')),
    'concurrency' => array('files' => array('tests/cross-domain-concurrency-regression.php'), 'needles' => array('FOR UPDATE')),
    'event_idempotency' => array('files' => array('tests/event-idempotency-regression.php'), 'needles' => array('idempot')),
    'woocommerce' => array('files' => array('includes/woo-automation.php'), 'needles' => array('woocommerce')),
    'failure_recovery' => array('files' => array('tests/test-integration-suite-report.php'), 'needles' => array('runtime')),
    'migration_scheduler' => array('files' => array('tests/test-migration-and-scheduler-contracts.php'), 'needles' => array('wp_schedule_event')),
    'security_runtime' => array('files' => array('tests/test-runtime-contracts.php'), 'needles' => array('WP_UnitTestCase')),
);

foreach ($requirements as $name => $spec) {
    $ok = count($spec['files']) === count($spec['needles']);
    if ($ok) {
        foreach ($spec['files'] as $i => $relative) {
            $path = $root . '/' . $relative;
            if (!is_file($path) || stripos(file_get_contents($path), $spec['needles'][$i]) === false) { $ok = false; break; }
        }
    }
    $checks[$name] = $ok;
}

$ci = file_get_contents($root . '/.github/workflows/php-tests.yml');
$installer = file_get_contents($root . '/bin/install-wp-tests.sh');
$checks['ci_runtime'] = strpos($ci, 'mysql:8.0') !== false
    && strpos($ci, 'WP_TESTS_DIR') !== false
    && strpos($ci, 'vendor/bin/phpunit') !== false
    && strpos($installer, 'WP_TESTS_DIR') !== false;

$failed = array_keys(array_filter($checks, static function($ok){ return !$ok; }));
foreach ($checks as $name => $ok) echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
echo 'RESULT ' . count($checks) . '/' . count($checks) . ' contract checks evaluated; failures=' . count($failed) . PHP_EOL;
exit($failed ? 1 : 0);
