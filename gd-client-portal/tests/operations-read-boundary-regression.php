<?php
if (!defined('ABSPATH')) { exit; }
$root=dirname(__DIR__); $gov=$root.'/includes/governance.php'; $src=file_get_contents($gov);
$checks=array(
 'governance uses automation service for rules'=>strpos($src,"gdcp_automation_service()->list_rules()")!==false,
 'governance uses automation service for stats'=>strpos($src,"gdcp_automation_service()->stats()")!==false,
 'governance uses automation service for analytics'=>strpos($src,"gdcp_automation_service()->analytics(30)")!==false,
 'governance uses automation service for queue listing'=>strpos($src,"gdcp_automation_service()->list_queue_by_status")!==false,
 'governance snapshot uses automation service rule lookup'=>strpos($src,"gdcp_automation_service()->get_rule($rule_id)")!==false,
 'governance has no direct automation queue SELECT'=>preg_match('/SELECT\s+\*\s+FROM\s+\{\$qt\}/i',$src)===0,
 'governance has no direct automation rules SELECT'=>preg_match('/SELECT\s+\*\s+FROM\s+\{\$rt\}/i',$src)===0,
 'governance has no direct automation log SELECT'=>preg_match('/SELECT\s+.*FROM\s+\{\$lt\}/i',$src)===0,
);
$pass=0; foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL')." - {$name}\n"; if($ok)$pass++;}
echo "Operations read boundary: {$pass}/".count($checks)."\n"; if($pass!==count($checks)) exit(1);
