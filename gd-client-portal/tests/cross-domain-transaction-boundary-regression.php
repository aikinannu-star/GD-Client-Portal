<?php
if (!defined('ABSPATH')) exit;
$root=dirname(__DIR__); $checks=array(
 array('Conditional project transition boundary','app/Repositories/ProjectRepository.php','transition_fields'),
 array('Project stage transition uses conditional update','app/Services/ProjectService.php','transition_fields($project_id,$project->current_stage,$data)'),
 array('Workflow Studio bulk status uses service','includes/studio.php','set_rule_status('),
 array('Workflow Studio import uses service','includes/studio.php','import_rule('),
 array('Workflow Studio duplicate uses service','includes/studio.php','duplicate_rule('),
 array('Governance restore uses automation service','includes/governance.php','restore_rule('),
 array('Governance snapshots lock source rule','includes/governance.php','FOR UPDATE'),
 array('Governance versions have unique rule/version key','includes/governance.php','UNIQUE KEY rule_version'),
); $failed=array();
foreach($checks as $c){$text=(string)@file_get_contents($root.'/'.$c[1]);if(strpos($text,$c[2])===false)$failed[]=$c[0];}
foreach(array('includes/studio.php') as $f){$text=file_get_contents($root.'/'.$f);if(preg_match('/\$wpdb->(?:insert|update|delete)\s*\(/',$text))$failed[]=$f.' contains direct persistence mutation';}
if($failed){fwrite(STDERR,"Cross-domain transaction boundary failed:\n- ".implode("\n- ",$failed)."\n");exit(1);} echo "Cross-domain transaction boundary passed.\n";
