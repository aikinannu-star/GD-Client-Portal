#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${1:?Database name required}"
DB_USER="${2:?Database user required}"
DB_PASS="${3:?Database password required}"
DB_HOST="${4:?Database host required}"
WP_VERSION="${5:-latest}"

WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"

rm -rf "$WP_CORE_DIR" "$WP_TESTS_DIR"
mkdir -p "$WP_CORE_DIR" "$WP_TESTS_DIR"

if [ "$WP_VERSION" = "latest" ]; then
  WP_DOWNLOAD="https://wordpress.org/latest.tar.gz"
else
  WP_DOWNLOAD="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
fi

curl -fsSL "$WP_DOWNLOAD" | tar xz --strip-components=1 -C "$WP_CORE_DIR"

# Export the official test suite directories with their expected layout.
svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes \
  "$WP_TESTS_DIR/includes"
svn export --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data \
  "$WP_TESTS_DIR/data"

cat > "$WP_TESTS_DIR/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'GD Client Portal Tests' );
define( 'WP_PHP_BINARY', 'php' );

// Define DB constants expected by WordPress test bootstrap.
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );

// Table prefix used by tests. Ensure it's defined to avoid undefined variable in bootstrap.
\$table_prefix = 'wp_';
PHP

echo "WordPress core installed at: $WP_CORE_DIR"
echo "WordPress test library installed at: $WP_TESTS_DIR"
