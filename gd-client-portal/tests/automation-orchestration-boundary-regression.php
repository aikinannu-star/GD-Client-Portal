<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../');
$root=dirname(__DIR__);
$svc=$root.'/includes/automation-service.php';
if(!is_file($svc)) exit("Automation orchestration boundary missing.\n");
$s=file_get_contents($svc);
foreach(array('retry_queue','cancel_queue') as $m){if(strpos($s,'function '.$m.'(')===false) exit("Missing automation service method: {$m}.\n");}
$in=file_get_contents($root.'/includes/inbox.php');
if(strpos($in,"\$wpdb->update(\$q,array('status'=>'queued'")!==false) exit("Inbox still directly mutates automation retry state.\n");
if(strpos($in,"\$wpdb->update(\$q,array('status'=>'cancelled'")!==false) exit("Inbox still directly mutates automation cancel state.\n");
$gov=file_get_contents($root.'/includes/governance.php');
if(strpos($gov,"\$wpdb->update(\$qt,array('status'=>'queued'")!==false) exit("Governance still directly mutates automation retry state.\n");
echo "Automation orchestration boundary passed.\n";
