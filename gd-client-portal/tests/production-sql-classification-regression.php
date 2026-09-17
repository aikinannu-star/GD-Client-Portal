<?php
/** Phase 1 production SQL classification contract. */
$root=dirname(__DIR__); $fail=array(); $pass=0;
$checks=array(
 'Inbox has no direct cross-domain query'=>function()use($root){$s=file_get_contents($root.'/includes/inbox.php');return !preg_match('/\$wpdb->(get_results|get_row|get_var|insert|update|delete|query)\b/',$s);},
 'Unified Search has no direct SQL'=>function()use($root){$s=file_get_contents($root.'/includes/unified-search.php');return !preg_match('/\$wpdb->(get_results|get_row|get_var|insert|update|delete|query)\b/',$s);},
 'Executive UI has no direct SQL'=>function()use($root){$s=file_get_contents($root.'/includes/executive.php');return !preg_match('/\$wpdb->(get_results|get_row|get_var|insert|update|delete|query)\b/',$s);},
 'Governance reads automation through service'=>function()use($root){$s=file_get_contents($root.'/includes/governance.php');return strpos($s,'gdcp_automation_service()->list_rules')!==false&&strpos($s,'gdcp_automation_service()->stats')!==false;},
 'Reporting project reads use project boundary'=>function()use($root){$s=file_get_contents($root.'/includes/reporting.php');return strpos($s,'gdcp_service')!==false && !preg_match('/\$wpdb->(get_results|get_row|get_var|insert|update|delete|query)\b/',$s);},
 'Inbox read service exists'=>function()use($root){return file_exists($root.'/includes/inbox-read-service.php')&&strpos(file_get_contents($root.'/includes/inbox-read-service.php'),'class GDCP_Inbox_Read_Service')!==false;},
 'Billing repository owns outstanding invoice query'=>function()use($root){return strpos(file_get_contents($root.'/includes/billing-repository.php'),'list_outstanding_for_scope')!==false;},
 'Automation repository owns scoped queue query'=>function()use($root){return strpos(file_get_contents($root.'/app/Repositories/AutomationRepository.php'),'list_queue_for_scope')!==false;},
);
foreach($checks as $name=>$fn){$ok=(bool)$fn();if($ok){$pass++;echo "PASS: $name\n";}else{$fail[]=$name;echo "FAIL: $name\n";}}
if($fail){fwrite(STDERR,"\n".count($fail)." SQL classification contract(s) failed.\n");exit(1);}echo "\n$pass SQL classification checks passed.\n";
