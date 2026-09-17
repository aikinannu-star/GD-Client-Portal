<?php
/**
 * Hardened PHPUnit bootstrap for a disposable WordPress test environment.
 */

$tests_dir = getenv('WP_TESTS_DIR');

if (!$tests_dir) {
    fwrite(STDERR, "WP_TESTS_DIR is required.\n");
    exit(1);
}

$tests_dir = rtrim($tests_dir, '/\\');

foreach (array(
    '/includes/functions.php',
    '/includes/bootstrap.php',
    '/wp-tests-config.php',
) as $required) {
    if (!file_exists($tests_dir . $required)) {
        fwrite(STDERR, "Missing WordPress test-suite requirement: " . $tests_dir . $required . "\n");
        exit(1);
    }
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    require_once dirname(__DIR__) . '/gd-client-portal.php';
});

require $tests_dir . '/includes/bootstrap.php';
