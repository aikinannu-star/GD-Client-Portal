<?php
// Static contract for safe security-audit investigation controls.
$repo = file_get_contents(dirname(__DIR__) . '/includes/security-audit-repository.php');
$obs = file_get_contents(dirname(__DIR__) . '/includes/production-observability.php');
$checks = array(
 'correlation filter' => strpos($repo, "correlation_id = %s") !== false,
 'severity filter' => strpos($repo, "severity = %s") !== false,
 'action filter' => strpos($repo, "action = %s") !== false,
 'time filters' => strpos($repo, "event_time >= %s") !== false && strpos($repo, "event_time <= %s") !== false,
 'integrity health' => strpos($repo, 'integrity_health') !== false,
 'tenant-safe request filters' => strpos($obs, 'gdcp_security_audit_investigation_filters_from_request') !== false && strpos($obs, 'current_tenant_id') !== false,
 'sensitive values excluded' => strpos($obs, 'Never persist secrets') !== false,
);
foreach ($checks as $name => $ok) echo ($ok ? 'PASS' : 'FAIL') . " - {$name}\n";
if (in_array(false, $checks, true)) exit(1);
