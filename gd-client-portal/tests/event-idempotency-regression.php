<?php
/**
 * Cross-domain event/idempotency contract checks for GD Client Portal.
 * Run with: php tests/event-idempotency-regression.php
 */
$root=dirname(__DIR__);
$notification_repo=file_get_contents($root.'/includes/notification-repository.php');
$notification_service=file_get_contents($root.'/includes/notification-service.php');
$notifications=file_get_contents($root.'/modules/notifications/index.php');
$woo=file_get_contents($root.'/includes/woo-automation.php');
$project_repo=file_get_contents($root.'/app/Repositories/ProjectRepository.php');
$project_service=file_get_contents($root.'/app/Services/ProjectService.php');
$sla=file_get_contents($root.'/includes/sla-service.php');
$support=file_get_contents($root.'/includes/support.php');

$checks=array(
    'notification schema supports nullable event keys' => strpos($notifications,'event_key varchar(100) NULL')!==false,
    'notification event key is uniquely scoped per user' => strpos($notifications,'UNIQUE KEY user_event_key (user_id,event_key)')!==false,
    'notification repository can find an existing event key' => strpos($notification_repo,'find_by_event_key')!==false && strpos($notification_repo,'WHERE user_id=%d AND event_key=%s')!==false,
    'notification service performs pre-insert idempotency lookup' => strpos($notification_service,'find_by_event_key($user_id,$event_key)')!==false,
    'notification service recovers concurrent unique-key races' => strpos($notification_service,'A concurrent request may have won the unique event-key insert race.')!==false,
    'legacy notification API accepts an event key without breaking callers' => strpos($notifications,'$event_key = \'\'')!==false && strpos($notifications,'$tenant_id,$event_key')!==false,
    'Woo project creation uses an atomic database lock' => strpos($woo,"SELECT GET_LOCK(%s,5)")!==false && strpos($woo,"SELECT RELEASE_LOCK(%s)")!==false,
    'Woo project creation rechecks after locking' => strpos($woo,'Re-check after acquiring the database lock')!==false,
    'Woo project persistence is routed through Project Service' => strpos($woo,"gdcp_service('project')")!==false && strpos($woo,'create_from_woocommerce')!==false,
    'Project Repository owns Woo project creation persistence' => strpos($project_repo,'public function create($data)')!==false,
    'Project Service exposes trusted Woo creation boundary' => strpos($project_service,'public function create_from_woocommerce($data)')!==false,
    'SLA escalation notification uses deterministic idempotency key' => strpos($sla, "'sla_'.absint(\$project->id).'_'.absint(\$sla->escalation_level+1)")!==false,
    'Support escalation notification uses deterministic idempotency key' => strpos($support, "'support_'." . '$ticket->id')!==false && strpos($support, 'md5((string)$ticket->due_at)')!==false,
);
$failed=array();
foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." — $name\n"; if(!$ok)$failed[]=$name; }
if($failed){ fwrite(STDERR,"\n".count($failed)." event/idempotency contract(s) failed.\n"); exit(1); }
echo "\nEvent/idempotency contract passed.\n";
