# GD Client Portal v7.6.0 — Application Lifecycle Architecture

This release adds a non-invasive application-kernel boundary around the existing WordPress bootstrap.

## What changed

- Bootstrap is now divided into explicit phases: `bootstrap`, `core`, `platform`, `features`, `compatibility`, and `ready`.
- Recoverable module-load failures are recorded in one lifecycle object as well as the existing persisted diagnostics.
- Loaded files/modules and per-phase bootstrap timings are available to the Platform Architecture screen.
- The module registry now validates missing dependencies and dependency cycles without changing the established load order.
- Existing WordPress hooks, legacy modules, repositories, services, AJAX handlers, REST routes, and authorization rules remain compatible.

## Why this is architectural

The plugin previously had a large procedural bootstrap with platform metadata layered on top. The lifecycle boundary provides a stable place for future module providers and domain migration without forcing an all-at-once rewrite.

## Safety

This pass does not introduce persistent caches, alter tenant authorization, change payment flows, or change database schemas. It is intended as an observability and orchestration foundation for incremental migration.
