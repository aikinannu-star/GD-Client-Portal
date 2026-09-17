# Phase 2 — Runtime Verification & Release Candidate

## Scope
This phase moves from static architecture verification toward execution against a disposable WordPress runtime and database.

## Preflight completed
- PHP syntax scan completed for the full plugin tree.
- Cross-domain concurrency contract executed successfully after aligning its assertion with the canonical Billing Service lock boundary.
- Event/idempotency standalone contract executed successfully.
- Runtime-readiness preflight verifies the repository contains the WordPress PHPUnit bootstrap, disposable MySQL CI service, WordPress test-suite installer, and PHPUnit execution path.

## Runtime environment status
A real WordPress PHPUnit runtime is **not available in the current execution environment**: `WP_TESTS_DIR` is not configured and Composer/PHPUnit dependencies are not installed locally. Therefore no claim is made that WordPress database integration tests have executed here.

The repository's CI workflow provisions:
- MySQL 8.0 disposable service
- WordPress core
- WordPress automated test suite
- Composer/PHPUnit
- isolated test database credentials

## Required runtime matrix
1. Bootstrap/plugin activation.
2. Tenant A/B isolation.
3. Project ownership and authorization.
4. Authenticated AJAX nonce/authorization paths.
5. Protected document/message/delivery downloads.
6. Database migrations and scheduler round-trip.
7. Workflow: intake → onboarding → project → collaboration → approval → delivery → billing → support → completion.
8. Payment replay/idempotency and overpayment rejection.
9. Contract/quote concurrency.
10. Approval/delivery concurrency.
11. Notification/event idempotency.
12. WooCommerce integration behavior.
13. Failure/recovery and rollback scenarios.

## Release gate
Do not deploy the release candidate until the disposable WordPress PHPUnit matrix passes and any runtime findings are reconciled.

Production WordPress.com remains unchanged by this phase.
