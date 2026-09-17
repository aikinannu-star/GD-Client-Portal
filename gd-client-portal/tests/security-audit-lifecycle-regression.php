<?php
/** Security audit lifecycle contract. */
$root = dirname(__DIR__);
$repo = file_get_contents($root . '/includes/security-audit-repository.php');
$main = file_get_contents($root . '/gd-client-portal.php');
$checks = array(
 'retention purge method' => strpos($repo, 'purge_older_than') !== false,
 'integrity hash columns' => strpos($repo, 'event_hash') !== false && strpos($repo, 'previous_hash') !== false,
 'chain verification' => strpos($repo, 'verify_chain') !== false,
 'scheduled retention hook' => strpos($main, 'gd_client_portal_security_audit_retention') !== false,
 'bounded retention' => strpos($repo, 'min(3650') !== false && strpos($repo, 'max(7') !== false,
 'no raw payload logging' => strpos($repo, "'message' => sanitize_text_field") !== false,
);
$failed=0; foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL') . ' — ' . $name . "\n"; if(!$ok)$failed++; }
exit($failed?1:0);
