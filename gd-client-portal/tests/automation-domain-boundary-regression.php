<?php
$root=dirname(__DIR__);
$targets=array(
 'includes/automation.php'=>array('gd_client_portal_automation_write_log','gdcp_automation_service()->create_queue','gdcp_automation_service()->update_queue','gdcp_automation_service()->claim_queue','gdcp_automation_service()->increment_run'),
 'includes/reliability.php'=>array('gdcp_automation_service()->retry_queue','gdcp_automation_service()->cancel_queue','gdcp_automation_service()->stats','gdcp_automation_service()->list_queue'),
 'includes/command-center.php'=>array('gdcp_automation_service()->get_queue','gdcp_automation_service()->retry_queue','gdcp_automation_service()->cancel_queue'),
);
foreach($targets as $file=>$needles){$s=file_get_contents($root.'/'.$file);foreach($needles as $needle)if(strpos($s,$needle)===false)throw new RuntimeException("Missing automation boundary call {$needle} in {$file}");}
foreach(array('includes/automation.php','includes/reliability.php','includes/command-center.php') as $file){$s=file_get_contents($root.'/'.$file);if(preg_match('/\$wpdb->(?:insert|update|delete)\s*\(.*gd_client_portal_automation_table/s',$s))throw new RuntimeException("Direct automation-table mutation remains in {$file}");}
$repo=file_get_contents($root.'/app/Repositories/AutomationRepository.php');foreach(array('create_rule','update_rule','delete_rule','create_queue','claim_queue','create_log','create_template') as $m)if(strpos($repo,'function '.$m.'(')===false)throw new RuntimeException("Repository method missing: {$m}");
echo "Automation domain boundary contract passed.\n";
