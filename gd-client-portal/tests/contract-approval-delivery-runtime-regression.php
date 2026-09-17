<?php
$root=dirname(__DIR__);
$files=array(
    'includes/contract-service.php',
    'includes/contract-repository.php',
    'app/Services/ApprovalService.php',
    'app/Repositories/ApprovalRepository.php',
    'includes/delivery-service.php',
    'includes/delivery-repository.php',
    'includes/delivery.php',
);
$checks=array(); $ok=true;
foreach($files as $file){
    $path=$root.'/'.$file; $pass=is_file($path);
    $checks[$file]=$pass; if(!$pass)$ok=false;
}
$contract=file_get_contents($root.'/includes/contract-service.php');
$approval=file_get_contents($root.'/app/Repositories/ApprovalRepository.php');
$delivery=file_get_contents($root.'/includes/delivery.php');
$checks['contract_decision_locks_row']=strpos($contract, '$this->repo()->find($id,true)')!==false;
$checks['approval_exact_version_query']=strpos($approval,"status='approved' AND version=%d")!==false;
$checks['delivery_finalize_exact_version']=strpos($delivery,'approved_for_project_version')!==false;
$checks['delivery_publish_uses_repository']=strpos($delivery,'gdcp_delivery_service()->publish')!==false;
foreach($checks as $v)if(!$v){$ok=false;break;}
echo ($ok?'Contract/approval/delivery runtime boundary passed':'Contract/approval/delivery runtime boundary FAILED').PHP_EOL;
foreach($checks as $name=>$pass)echo ($pass?'PASS ':'FAIL ').$name.PHP_EOL;
exit($ok?0:1);
