<?php
if (!defined('ABSPATH')) exit;
/** Production baseline boundary contract: cross-domain reads must use canonical services. */
function gdcp_production_baseline_regression(){
    $root=dirname(__DIR__); $checks=array();
    $checks['approval repository exposes exact-version lookup']=strpos(file_get_contents($root.'/app/Repositories/ApprovalRepository.php'),'find_approved_for_project_version')!==false;
    $checks['approval service exposes exact-version lookup']=strpos(file_get_contents($root.'/app/Services/ApprovalService.php'),'approved_for_project_version')!==false;
    $delivery=file_get_contents($root.'/includes/delivery.php');
    $checks['delivery does not query approval table directly']=strpos($delivery,'SELECT id FROM {$approval_table}')===false && strpos($delivery,'get_approval_table_name')===false;
    $contract=file_get_contents($root.'/includes/contract-service.php');
    $checks['contract service does not query quote table directly']=strpos($contract,'SELECT * FROM {$qt}')===false && strpos($contract,'gd_client_portal_billing_quotes_table')===false;
    $checks['billing quote access supports locking']=strpos(file_get_contents($root.'/includes/billing-service.php'),'get_quote_for_access($id,$lock=false)')!==false;
    $passed=0; foreach($checks as $name=>$ok){ if($ok)$passed++; echo ($ok?'PASS':'FAIL').' - '.$name."\n"; }
    echo "Production baseline contract: {$passed}/".count($checks)."\n"; return $passed===count($checks);
}
if (defined('GDCP_RUN_PRODUCTION_BASELINE_REGRESSION') && GDCP_RUN_PRODUCTION_BASELINE_REGRESSION) gdcp_production_baseline_regression();
