<?php
if (!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
$root=dirname(__DIR__); $file=$root.'/includes/security-incident-repository.php'; if(!is_file($file)) die("FAIL missing incident repository\n");
$src=file_get_contents($file); $checks=array(
 'dedicated incident table'=>strpos($src,'gdcp_security_incidents')!==false,
 'append-only action table'=>strpos($src,'gdcp_security_incident_actions')!==false,
 'action hash chain'=>strpos($src,'action_hash')!==false && strpos($src,'previous_hash')!==false,
 'safe evidence reference'=>strpos($src,'sanitize_key($evidence_ref)')!==false,
 'state transitions are action based'=>strpos($src, "'acknowledged'")!==false && strpos($src, "'resolved'")!==false && strpos($src, "'reopened'")!==false,
 'bounded incident listing'=>strpos($src,'min(200,max(1,absint($limit)))')!==false,
 'integrity verification'=>strpos($src,'verify_actions')!==false,
); $ok=true; foreach($checks as $n=>$v){echo ($v?'PASS ':'FAIL ').$n."\n";$ok=$ok&&$v;} exit($ok?0:1);
