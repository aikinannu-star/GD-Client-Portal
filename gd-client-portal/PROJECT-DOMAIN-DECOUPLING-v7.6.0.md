# Project Domain Decoupling — v7.6.0

## Purpose

The Projects domain is now the first feature domain to use the repository/service boundary as its canonical persistence path while retaining compatibility with the existing procedural API.

## Changes

- `GDCP_Project_Repository` is now the authoritative project read boundary.
- Added bounded `find_many()` retrieval for bulk project hydration.
- Added repository-owned visibility rules matching the existing tenant/user behavior.
- `gd_client_portal_get_project_by_id()` now delegates to the repository.
- `gd_client_portal_get_visible_projects()` now delegates to the repository.
- `GDCP_Project_Service::get_many()` exposes bulk retrieval to future controllers/services.
- Existing shortcodes, AJAX endpoints, hooks, and legacy function names remain intact.

## Architectural direction

The intended dependency direction is:

`WordPress/UI → Controller → Project Service → Project Repository → $wpdb`

Legacy procedural functions remain adapters during migration. New project functionality should use `gdcp_service('project')` or `gdcp_repository('project')` rather than adding new direct `$wpdb` calls.

## Safety boundary

This pass does not migrate trusted background writes or WooCommerce provisioning into the service layer. Those paths can run without an authenticated user and require an explicit internal-command authorization model before being moved. This avoids accidentally breaking cron/order automation while the architecture is being decoupled.
