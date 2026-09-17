<?php
$root = dirname(__DIR__);
$automation = file_get_contents($root . '/includes/automation.php');
$installer = file_get_contents($root . '/includes/installer.php');
$control = file_get_contents($root . '/includes/platform-control.php');
$checks = array(
    'daily scheduler hook exists' => strpos($automation, "gd_client_portal_automation_daily") !== false,
    'reliability hook exists' => strpos($automation, "gd_client_portal_automation_reliability_tick") !== false,
    'queue runner hook exists' => strpos($automation, "gd_client_portal_automation_queue_runner") !== false,
    'single-event scheduling used for queued work' => strpos($automation, 'wp_schedule_single_event') !== false,
    'stale queue recovery reschedules work' => strpos($automation, 'gd_client_portal_automation_recover_stale_queue') !== false && strpos($automation, 'wp_schedule_single_event(time()+5') !== false,
    'retry uses bounded backoff' => strpos($automation, 'min(3600,60*pow(2,$attempts-1))') !== false,
    'installer registers reliability hook' => strpos($installer, 'gd_client_portal_automation_reliability_tick') !== false,
    'control center can restore reliability heartbeat' => strpos($control, 'gd_client_portal_automation_reliability_tick') !== false,
);
$failed = array();
foreach ($checks as $name => $ok) { echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL; if (!$ok) $failed[] = $name; }
exit($failed ? 1 : 0);
