<?php
/**
 * Phase 2 runtime-readiness checks. These are static/preflight checks only;
 * they never connect to a production database.
 * Run with: php tests/phase2-runtime-readiness-regression.php
 */
$root=dirname(__DIR__);
$checks=array(
    'PHP runtime is available' => PHP_VERSION_ID >= 80100,
    'plugin bootstrap exists' => is_file($root.'/gd-client-portal.php'),
    'runtime PHPUnit bootstrap requires disposable test suite' => strpos(file_get_contents($root.'/tests/bootstrap.php'),'WP_TESTS_DIR')!==false,
    'CI provisions disposable MySQL' => strpos(file_get_contents($root.'/.github/workflows/php-tests.yml'),'mysql:8.0')!==false,
    'CI provisions WordPress test suite' => strpos(file_get_contents($root.'/.github/workflows/php-tests.yml'),'install-wp-tests.sh')!==false,
    'CI executes PHPUnit' => strpos(file_get_contents($root.'/.github/workflows/php-tests.yml'),'vendor/bin/phpunit')!==false,
    'runtime integration contract suite exists' => is_file($root.'/tests/test-runtime-contracts.php'),
    'security runtime contract suite exists' => is_file($root.'/tests/class-runtime-integration-contracts.php'),
);
$failed=array();
foreach($checks as $name=>$ok){ echo ($ok?'PASS':'FAIL')." — $name\n"; if(!$ok)$failed[]=$name; }
if($failed){ fwrite(STDERR,"\n".count($failed)." Phase 2 runtime-readiness check(s) failed.\n"); exit(1); }
echo "\nPhase 2 runtime-readiness preflight passed.\n";
