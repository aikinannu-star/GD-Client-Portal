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

# Verify WordPress core was extracted correctly. The PHPMailer filename
# differs between WordPress releases; accept either variant but ensure
# core was extracted by checking version.php.
if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
  echo "ERROR: WordPress core was not extracted correctly" >&2
  echo "Listing $WP_CORE_DIR:" >&2
  ls -la "$WP_CORE_DIR" || true
  echo "Listing $WP_CORE_DIR/wp-includes:" >&2
  ls -la "$WP_CORE_DIR/wp-includes" || true
  echo "Dumping /tmp/wp-install.log (if present):" >&2
  if [ -f /tmp/wp-install.log ]; then
    tail -n 200 /tmp/wp-install.log >&2 || true
  fi
  exit 1
fi

if [ ! -f "$WP_CORE_DIR/wp-includes/class-wp-phpmailer.php" ] && \
   [ ! -f "$WP_CORE_DIR/wp-includes/class-phpmailer.php" ]; then
  echo "ERROR: WordPress core does not contain a recognized PHPMailer implementation" >&2
  echo "Listing $WP_CORE_DIR/wp-includes:" >&2
  ls -la "$WP_CORE_DIR/wp-includes" || true
  exit 1
fi

# Use a test suite compatible with the downloaded WordPress core.
if [ "$WP_VERSION" = "latest" ]; then
  WP_TESTS_REF="trunk"
else
  WP_TESTS_REF="branches/${WP_VERSION}"
fi

WP_TESTS_SVN_BASE="https://develop.svn.wordpress.org/${WP_TESTS_REF}/tests/phpunit"

svn export --quiet "${WP_TESTS_SVN_BASE}/includes" \
  "$WP_TESTS_DIR/includes"
svn export --quiet "${WP_TESTS_SVN_BASE}/data" \
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
