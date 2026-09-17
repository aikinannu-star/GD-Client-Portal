<?php
/** Standalone contract checks for the workflow runtime suite. */
$root = dirname(__DIR__);
$service = file_get_contents($root.'/app/Services/ProjectService.php');
$workflow = file_get_contents($root.'/includes/service-projects.php');
$test = file_get_contents(__DIR__.'/test-workflow-runtime-contracts.php');
$checks = array(
    'workflow-runtime-test-exists' => strpos($test, 'class GDCP_Workflow_Runtime_Contracts_Test') !== false,
    'canonical-stage-service-boundary' => strpos($service, 'public function update_stage(') !== false && strpos($service, 'transition_stage(') !== false,
    'workflow-stage-validation' => strpos($service, 'in_array($stage,$workflow,true)') !== false,
    'progress-is-clamped' => strpos($service, 'max(0,min(100,absint($progress)))') !== false,
    'atomic-stage-transition' => strpos($service, 'transition_fields($project_id,$project->current_stage,$data)') !== false,
    'invalid-stage-runtime-covered' => strpos($test, 'test_invalid_stage_is_rejected_without_mutating_project') !== false,
    'cross-tenant-runtime-covered' => strpos($test, 'test_cross_tenant_user_cannot_transition_project_stage') !== false,
    'duplicate-event-runtime-covered' => strpos($test, 'test_same_stage_retry_does_not_emit_a_second_stage_change_event') !== false,
    'terminal-completion-runtime-covered' => strpos($test, "'completed'", strpos($test, 'test_complete_workflow_sequence_updates_stage_and_progress')) !== false,
);
$failed=array(); foreach($checks as $name=>$ok){if(!$ok)$failed[]=$name;}
echo sprintf("Workflow runtime regression: %d/%d PASS\n", count($checks)-count($failed), count($checks));
foreach($checks as $name=>$ok) echo ($ok?'PASS ':'FAIL ').$name."\n";
if($failed) exit(1);
