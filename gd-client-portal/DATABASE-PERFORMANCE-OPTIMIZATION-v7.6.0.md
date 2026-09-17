# GD Client Portal v7.6.0 — Database Performance Optimization

## Scope

This release adds a conservative database-index optimization migration for existing installations. It targets the access patterns used by the portal's tenant, project, workflow, billing, support, collaboration, delivery, calendar, document, and automation screens.

## What changed

- Platform schema version advanced to `6`.
- Added idempotent index discovery using `SHOW INDEX` before every `ALTER TABLE`.
- Added compound indexes for frequent combinations such as:
  - tenant + user + project
  - tenant + status + date
  - project + status + date/version
  - ticket + creation date
  - automation status + run date
- Missing optional tables are safely skipped so the migration does not create unrelated tables.
- Existing data is not rewritten or deleted.
- Existing indexes are preserved.
- Added request-local memoization to SLA and assignment reads, reducing repeated identical queries during reporting/calendar/collaboration rendering.

## Safety

The migration is additive only. It uses sanitized table/index identifiers and checks that a table and index do not already exist before attempting an index creation.

No authorization, tenant-boundary, payment, billing, or private-file logic was changed by the database optimization work.

## Validation

- PHP syntax validation performed across the complete plugin source.
- Package root remains `gd-client-portal/` for WordPress compatibility.
- ZIP integrity verified after packaging.

## Runtime note

No production latency benchmark is claimed here because a live WordPress/MySQL workload is not available in this build environment. The work is based on static query-pattern analysis and safe index coverage rather than synthetic benchmark results.
