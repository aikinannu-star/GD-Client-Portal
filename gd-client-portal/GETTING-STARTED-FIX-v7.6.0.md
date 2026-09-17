# Getting Started Routing Fix — v7.6.0

## Issue
The Getting Started screen could continue resolving to a public/theme 404 page even after the submenu registration order was corrected.

## Fix
- Registers the Getting Started submenu at priority 11, immediately after the main plugin menu.
- Uses the same `gd_client_portal_access_admin` capability as the plugin workspace.
- Adds a priority-999 safety check that restores the submenu only if another component removed it.
- Adds a guarded legacy-route redirect for authenticated portal administrators visiting `/getting-started/` or `/get-started/`.
- Adds a canonical dashboard link so the intended `wp-admin/admin.php?page=gd-client-portal-getting-started` route is explicit.

The legacy redirect is limited to logged-in users with GD Client Portal admin access and never affects ordinary public visitors.
