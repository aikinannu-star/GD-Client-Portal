<?php
$root=dirname(__DIR__); $contracts=file_get_contents($root.'/includes/contracts.php'); $service=file_get_contents($root.'/includes/contract-service.php'); $repo=file_get_contents($root.'/includes/contract-repository.php'); $bootstrap=file_get_contents($root.'/gd-client-portal.php'); $fail=[];
foreach(['contract-repository.php','contract-service.php'] as $f) if(!is_file($root.'/includes/'.$f))$fail[]='missing '.$f;
if(strpos($bootstrap,"'contract-repository','contract-service'")===false)$fail[]='bootstrap missing contract boundary';
if(preg_match('/\$wpdb->(insert|update|delete)\s*\(/i',$contracts))$fail[]='direct contract persistence remains in contracts.php';
foreach(['gdcp_contract_service()->create_from_quote','gdcp_contract_service()->create_sent','gdcp_contract_service()->decide','gdcp_contract_service()->send'] as $needle) if(strpos($contracts,$needle)===false)$fail[]='missing service delegation: '.$needle;
foreach(['latest_for_quote','insert_signature'] as $needle) if(strpos($service,$needle)===false)$fail[]='service missing contract capability: '.$needle;
foreach(['FOR UPDATE'] as $needle) if(strpos($repo,$needle)===false)$fail[]='repository missing concurrency capability: '.$needle;
foreach(['insert(array','update($id','insert_signature'] as $needle) if(strpos($repo,$needle)===false)$fail[]='repository missing persistence method: '.$needle;
if($fail){echo "Contract boundary regression FAILED\n".implode("\n",$fail)."\n";exit(1);} echo "Contract boundary regression PASSED\n";
