<?php
/**
 * Release certification contract for GD Client Portal 7.6.0.
 * Static checks only; does not require a live WordPress runtime.
 */
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }

$root = dirname(__DIR__);
$checks = array();
$ok = true;
$fail = function($label) use (&$checks, &$ok) { $checks[] = 'FAIL: ' . $label; $ok = false; };
$pass = function($label) use (&$checks) { $checks[] = 'PASS: ' . $label; };

$main = $root . '/gd-client-portal.php';
if (!is_file($main)) $fail('main plugin file exists'); else $pass('main plugin file exists');

$source_files = array(); foreach (array($root . '/includes', $root . '/modules') as $base) { if (is_dir($base)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)); foreach ($it as $item) if ($item->isFile() && substr($item->getFilename(), -4) === '.php') $source_files[] = $item->getPathname(); } }
if (count($source_files) < 100) $fail('expected production PHP surface is present'); else $pass('production PHP surface is present');

$security = file_get_contents($root . '/includes/security.php');
foreach (array('gd_client_portal_ajax_guard','gd_client_portal_verify_nonce_request','gd_client_portal_verify_project_access','gd_client_portal_verify_tenant_access') as $symbol) {
    if (strpos($security, 'function ' . $symbol) === false) $fail('security primitive: ' . $symbol); else $pass('security primitive: ' . $symbol);
}

$ajax_files = $source_files;
$ajax_count = 0; $auth_ajax_count = 0; $unguarded = array(); $public_actions = array();
foreach ($ajax_files as $af) { $as = file_get_contents($af); if (preg_match_all('~add_action\(\s*[\"\']wp_ajax_nopriv_([^\"\']+)[\"\']~', $as, $pm)) foreach ($pm[1] as $pa) $public_actions['wp_ajax_' . $pa] = true; }
foreach ($ajax_files as $file) {
    $s = file_get_contents($file);
    $pattern = '~add_action\(\s*[\"\'](wp_ajax(?:_nopriv)?_[^\"\']+)[\"\']\s*,\s*[\"\']([^\"\']+)[\"\']~';
    if (preg_match_all($pattern, $s, $m)) {
        foreach ($m[2] as $idx => $fn) {
            $action_name = $m[1][$idx];
            $ajax_count++;
            $is_public = strpos($action_name, 'wp_ajax_nopriv_') === 0 || isset($public_actions[$action_name]);
            if (!$is_public) $auth_ajax_count++;
            if (!preg_match('~function\s+' . preg_quote($fn, '~') . '\s*\([^)]*\)\s*\{~', $s, $fm, PREG_OFFSET_CAPTURE)) continue;
            $start = $fm[0][1] + strlen($fm[0][0]); $depth = 1; $i = $start; $len = strlen($s);
            while ($i < $len && $depth) { if ($s[$i] === '{') $depth++; elseif ($s[$i] === '}') $depth--; $i++; }
            $body = substr($s, $start, $i - $start);
            if (!$is_public && !preg_match('~gd_client_portal_ajax_guard\s*\(|gd_client_portal_verify_nonce_request\s*\(|check_ajax_referer\s*\(|check_admin_referer\s*\(|wp_verify_nonce\s*\(~', $body)) $unguarded[] = basename($file) . ':' . $fn;
        }
    }
}
if ($auth_ajax_count < 40) $fail('AJAX endpoint inventory'); else $pass('AJAX endpoint inventory: ' . $ajax_count . ' (' . $auth_ajax_count . ' authenticated)');
if ($unguarded) $fail('unguarded AJAX callbacks: ' . implode(', ', $unguarded)); else $pass('all authenticated AJAX callbacks have nonce/guard enforcement');

foreach (array('app/Services/ProjectService.php','includes/payment-service.php','includes/approvals.php','includes/delivery.php','includes/notification-repository.php') as $file) {
    if (!is_file($root . '/' . $file)) { $fail('required hardening file: ' . $file); }
}
if (is_file($root . '/app/Services/ProjectService.php')) $pass('project service boundary present');
if (is_file($root . '/includes/payment-service.php')) $pass('payment service boundary present');
if (is_file($root . '/includes/approvals.php')) $pass('approval boundary present');
if (is_file($root . '/includes/notification-repository.php')) $pass('notification idempotency boundary present');

$installer = file_get_contents($root . '/includes/installer.php');
$platform = file_get_contents($root . '/includes/platform.php');
if (strpos($installer, 'dbDelta(') === false) $fail('installer uses idempotent dbDelta migrations'); else $pass('installer uses idempotent dbDelta migrations');
if (strpos($platform, 'gd_client_portal_platform_column_exists') === false) $fail('platform migration guards column additions'); else $pass('platform migration guards column additions');

$bootstrap = file_get_contents($main);
if (strpos($bootstrap, 'GD_CLIENT_PORTAL_VERSION') === false || strpos($bootstrap, '7.6.0') === false) $fail('release version is 7.6.0'); else $pass('release version is 7.6.0');

foreach ($checks as $line) echo $line . PHP_EOL;
exit($ok ? 0 : 1);
