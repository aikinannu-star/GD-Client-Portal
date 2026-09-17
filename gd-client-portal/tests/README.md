# GD Client Portal Test Infrastructure

## Purpose

These tests are designed for a dedicated WordPress PHPUnit environment.

Do not run them against a production WordPress database.

## Requirements

- PHP compatible with the supported WordPress version
- PHPUnit
- WordPress automated test suite
- Dedicated disposable test database

## Environment

Set:

- `WP_TESTS_DIR` — path to the WordPress test library

Then execute PHPUnit using `phpunit.xml.dist`.

## Coverage roadmap

The initial executable suite validates runtime integration contracts.

The next test expansion should add:

1. Tenant A/Tenant B isolation tests
2. Project ownership authorization tests
3. Unauthorized AJAX mutation tests
4. Protected file authorization tests
5. Database migration tests
6. Scheduler/automation tests

## Expanded executable coverage

- Tenant identity and isolation contracts
- Project persistence and authorization contracts
- AJAX nonce and authorization contracts
- Protected-file runtime contracts
- Version/schema metadata and scheduler round-trip contracts

Run these only against a dedicated disposable WordPress test database.

## CI execution

The repository now includes a GitHub Actions workflow that provisions:

- A disposable MySQL 8.0 service
- A temporary WordPress core installation
- The WordPress automated test-suite library
- Composer/PHPUnit dependencies

The workflow runs syntax checks first, then executes PHPUnit against the
disposable database. No production database credentials are used or required.

## CI hardening

The CI workflow now validates the WordPress test-suite layout before PHPUnit
starts and uploads PHPUnit output as a build artifact even when tests fail.

Additional environment tests verify:

- WordPress runtime bootstrapping
- Plugin loading
- Database connectivity
- Core integration contract execution

This improves failure diagnosis while keeping all database operations inside the
disposable CI MySQL service.
