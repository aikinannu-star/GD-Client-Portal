<?php
$root=dirname(__DIR__); $fail=[];
$repo=file_get_contents($root.'/includes/message-repository.php');
$service=file_get_contents($root.'/includes/message-service.php');
$projects=file_get_contents($root.'/includes/service-projects.php');
$comm=file_get_contents($root.'/includes/communications.php');
$inbox=file_get_contents($root.'/includes/inbox.php');
$admin=file_get_contents($root.'/includes/admin.php');
$installer=file_get_contents($root.'/includes/installer.php');
$bootstrap=file_get_contents($root.'/gd-client-portal.php');
$checks=[
 'repository exists'=>strpos($repo,'final class GDCP_Message_Repository')!==false,
 'service exists'=>strpos($service,'final class GDCP_Message_Service')!==false,
 'service creates messages'=>strpos($service,'public function create')!==false && strpos($service,'->insert($row)')!==false,
 'event key lookup'=>strpos($repo,'find_by_event_key')!==false && strpos($service,"'event_key'")!==false,
 'project workspace uses service'=>strpos($projects,"gdcp_message_service()->list_for_project")!==false,
 'message AJAX uses service'=>strpos($projects,'gdcp_message_service()->create')!==false,
 'message download uses service'=>strpos($projects,'gdcp_message_service()->find($message_id)')!==false,
 'communications uses service'=>strpos($comm,'gdcp_message_service()->list_for_tenant')!==false,
 'inbox uses service'=>strpos($inbox,'gdcp_message_service()->list_for_user')!==false,
 'admin uses service'=>strpos($admin,'gdcp_message_service()->list_for_tenant')!==false,
 'schema has event key'=>strpos($installer,'event_key varchar(64)')!==false && strpos($installer,'project_event_key')!==false,
 'bootstrap loads boundary'=>strpos($bootstrap,"'message-repository','message-service'")!==false,
];
foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." — $name\n";if(!$ok)$fail[]=$name;}
if($fail){fwrite(STDERR,"Message boundary regression failed: ".implode(', ',$fail)."\n");exit(1);}echo "Message boundary regression passed.\n";
