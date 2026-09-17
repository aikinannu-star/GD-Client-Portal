<?php
// Static architecture contract: canonical includes repositories must be loaded without
// duplicate app/Repositories definitions, and the registry must resolve canonical domains.
$main=file_get_contents(__DIR__.'/../gd-client-portal.php');
foreach(array(
    "'app/Repositories/NotificationRepository.php'",
    "'app/Repositories/BillingRepository.php'",
    "'app/Repositories/DocumentRepository.php'",
    "'app/Repositories/SupportRepository.php'",
) as $legacy){
    if(strpos($main,$legacy)!==false) throw new RuntimeException('Legacy duplicate repository remains in bootstrap: '.$legacy);
}
$registry=file_get_contents(__DIR__.'/../app/Repositories/RepositoryRegistry.php');
foreach(array(
    "'notification'=>'GDCP_Notifications_Repository'",
    "'billing'=>'GDCP_Billing_Repository'",
    "'document'=>'GDCP_Document_Repository'",
    "'support'=>'GDCP_Support_Repository'",
    "'collaboration'=>'GDCP_Collaboration_Repository'",
    "'intake'=>'GDCP_Intake_Repository'",
    "'request'=>'GDCP_Request_Repository'",
    "'payment'=>'GDCP_Payment_Repository'",
    "'feedback'=>'GDCP_Feedback_Repository'",
    "'sla'=>'GDCP_SLA_Repository'",
) as $mapping){
    if(strpos($registry,$mapping)===false) throw new RuntimeException('Registry mapping missing: '.$mapping);
}
foreach(array(
    array('../includes/billing-repository.php','public function invoice('),
    array('../includes/billing-repository.php','public function invoices('),
    array('../includes/billing-repository.php','public function insert('),
    array('../includes/support-repository.php','public function ticket('),
    array('../includes/support-repository.php','public function tickets('),
) as $contract){
    if(strpos(file_get_contents(__DIR__.'/'.$contract[0]),$contract[1])===false) throw new RuntimeException('Compatibility API missing: '.$contract[1]);
}
echo "Repository registry consolidation contract passed.\n";
