# Modules — GD Client Portal

This document describes the recommended structure and steps to create modules for the GD Client Portal plugin.

Structure (per module)

modules/<module-slug>/
- index.php            — module bootstrap (register shortcodes, hooks, REST endpoints)
- assets/              — CSS/JS for module (optional)
- templates/           — view templates used by the module
- includes/            — internal helpers or AJAX handlers (optional)
- README.md            — module-specific notes (optional)

Guidelines

- Autoloading: The main plugin now auto-loads any `modules/*/index.php` file. Place your module index at `modules/<slug>/index.php` and it will be included during bootstrap.

- Security: Protect module content with `gd_client_portal_verify_request()` from `includes/security.php`.

- Views: Use `gd_client_portal_load_view()` from `includes/helpers.php` to include templates from the central `templates/` directory. Modules may also use their own `templates/` folder and include them directly.

- Assets: Register module assets using the plugin's asset conventions (see `includes/assets.php`) or enqueue them directly inside your module's hooks.

- AJAX/REST: Use `wp_ajax` / `wp_ajax_nopriv` or `register_rest_route()` for dynamic endpoints. Always validate nonces and capability checks.

Example

Copy `modules/_template/` to `modules/projects/` and customize `index.php` and templates to implement the project module.

