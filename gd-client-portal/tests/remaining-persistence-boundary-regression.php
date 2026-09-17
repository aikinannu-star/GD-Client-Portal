<?php
/** Static regression contract for the final legacy persistence-boundary cleanup. */
$root=dirname(__DIR__);
$checks=array(
 array('Lifecycle no direct billing/approval/feedback reads', function() use($root){$s=file_get_contents($root.'/includes/lifecycle.php');return strpos($s,'$wpdb->get_row')===false && strpos($s,'gd_client_portal_billing_quotes_table')===false && strpos($s,'gd_client_portal_feedback_table')===false;}),
 array('Studio no direct automation-rule persistence/read', function() use($root){$s=file_get_contents($root.'/includes/studio.php');return strpos($s,'$wpdb->')===false && strpos($s,'gd_client_portal_automation_table')===false;}),
 array('Lifecycle automation uses billing service', function() use($root){$s=file_get_contents($root.'/includes/lifecycle-automation.php');return strpos($s,'gdcp_billing_service()')!==false;}),
 array('Command Center uses automation service for queue', function() use($root){$s=file_get_contents($root.'/includes/command-center.php');return strpos($s,'list_queue_by_status')!==false && strpos($s,'gd_client_portal_automation_table(\'queue\')')===false;}),
 array('Command Center uses approval service', function() use($root){$s=file_get_contents($root.'/includes/command-center.php');return strpos($s,'pending_for_tenant')!==false && strpos($s,'gd_client_portal_get_approval_table_name')===false;}),
 array('Inbox automation item uses service', function() use($root){$s=file_get_contents($root.'/includes/inbox.php');return strpos($s,'gdcp_automation_service()->get_queue')!==false;}),
 array('Billing service exposes project-scoped reads', function() use($root){$s=file_get_contents($root.'/includes/billing-service.php');return strpos($s,'repo_latest_quote_for_project')!==false && strpos($s,'repo_latest_invoice_for_project')!==false;}),
 array('Approval service exposes pending/latest reads', function() use($root){$s=file_get_contents($root.'/app/Services/ApprovalService.php');return strpos($s,'latest_for_project')!==false && strpos($s,'pending_for_tenant')!==false;}),
);
$failed=array();foreach($checks as $c){$ok=(bool)call_user_func($c[1]);echo ($ok?'PASS: ':'FAIL: ').$c[0].PHP_EOL;if(!$ok)$failed[]=$c[0];}
if($failed){fwrite(STDERR,count($failed).' check(s) failed.'.PHP_EOL);exit(1);}echo count($checks).' remaining persistence-boundary checks passed.'.PHP_EOL;
