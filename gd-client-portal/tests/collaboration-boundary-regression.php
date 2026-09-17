<?php
$root=dirname(__DIR__); $collab=file_get_contents($root.'/includes/collaboration.php'); $automation=file_get_contents($root.'/includes/automation.php'); $repo=file_get_contents($root.'/includes/collaboration-repository.php'); $service=file_get_contents($root.'/includes/collaboration-service.php'); $fail=array();
if(strpos($collab,'gdcp_collaboration_service()')===false)$fail[]='collaboration entry points do not use service';
if(preg_match('/\$wpdb->(insert|update|delete)\s*\(\s*gd_client_portal_collab_table/s',$collab))$fail[]='legacy collaboration mutation remains';
if(preg_match('/\$wpdb->(insert|update|delete)\s*\(\s*gd_client_portal_collab_table/s',$automation))$fail[]='automation still mutates collaboration persistence directly';
foreach(array('create_task','update_task','delete_task','create_update','create_note') as $m)if(strpos($service,$m.'(')===false)$fail[]='service missing '.$m;
foreach(array('insert_task','update_task','delete_task','insert_update','insert_note') as $m)if(strpos($repo,$m.'(')===false)$fail[]='repository missing '.$m;
if($fail){fwrite(STDERR,"Collaboration boundary failed:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "Collaboration boundary contract passed.\n";
