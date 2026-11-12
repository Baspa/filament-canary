# Changelog

All notable changes to `:package_name` will be documented in this file.

## v1.3.0 - 2026-11-12

### Requirements

- **PHP 8.3+ and Laravel 12 or 13** (Filament 4 and 5, as before). Composer keeps PHP 8.2 and Laravel 11 projects on 1.2.x. CI now also tests PHP 8.5 and Pest 5. (#24, #28)

### Fixed

- **`php artisan config:cache` works again.** `canary:install` now generates invokable resolver classes in `tests/Canary/` instead of writing closures into `config/filament-canary.php`. It keeps the option docs, and keeps your other settings when it overwrites an existing config. (#14, #21, #31)
  
- **Tenant panels work out of the box.**
  
  - The acting user is resolved first, and tenant resolvers receive `($panel, $user)`.
  - The tenant is created once per panel.
  - The default tenant attaches the acting user through a many-to-many members relation.
  
  (#17, #31)
  
- **A sweep that tested nothing fails.** Finding no pages, or no page passing, is now a failure. The new `fail_on_skipped` option and `--fail-on-skipped` flag make skipped pages fail too. (#15, #30)
  
- **Lazy content is rendered.** Lazy widgets, lazy relation managers and deferred tables are rendered during the sweep, so their errors are caught. (#18, #30)
  
- **Stricter status rules.**
  
  - Other 4xx responses for the authorized user fail.
  - A guest response that isn't a redirect, 401 or 403 fails.
  - 404 hints point to the actual cause.
  
  (#19, #30)
  
- **The right pages are swept.**
  
  - The profile page is now swept.
  - Filament's redirect-only `home` and `tenant` routes are skipped.
  - `exclude` works for Page classes and for Resource classes (excluding a Resource never worked before).
  - Nested resources and multi-domain panels get the right resource and model.
  
  (#16, #29)
  

### Changed

- **Expect new failures after upgrading.** Rendering lazy content and stricter rules can surface failures that were hidden before.
- **Invalid resolver config is reported.** An `acting_as` or `tenant` value that isn't callable, names a class that can't be autoloaded, or returns the wrong type now gives a clear skip reason instead of silently falling back.
- **`canary:install` respects `panels.only` and `panels.except`.** It only proposes `assignRole()` when the user model has it, and only generates a tenant class when the default tenant can't attach the acting user.

### Docs

- **README updated for the new rules.** It covers resolvers, which pages are swept, and a CI example. The banner is smaller. (#25, #32)

**Full changelog:** https://github.com/Baspa/filament-canary/compare/v1.2.0...v1.3.0

## v1.2.0 - 2026-06-25

### Added — safe to run against real databases

- **The sweep runs in a transaction and rolls it back**, so the users/records it creates via factories are never persisted (default connection). Opt out with `use_transaction => false`.
- **It refuses to run in the `production` environment** — a rollback can't undo the other side effects of requesting every page as an admin (queued jobs, mail, audit logs, writes on secondary connections), so Canary won't run there.

**Full changelog:** https://github.com/Baspa/filament-canary/compare/v1.1.0...v1.2.0

## v1.1.0 - 2026-06-25

### Added

- **Clearer failures** — when the acting user, a record, or a tenant can't be created, the real exception is now shown in the skip reason (e.g. `could not create an authorized user for panel [admin]: Could not verify the hashed value's configuration.`) instead of a generic "no authorized user" / "no factory" skip. (#4)

### Changed (CI / security)

- All workflow actions are pinned to full-length commit SHAs, workflows declare least-privilege `permissions`, and a `SECURITY.md` disclosure policy was added. (#5)

### Docs

- Why a runtime sweep beats AI-generated tests, and why it's not "AI vs. Canary".
- How to support non-standard auth (custom API/session guards) by binding your own `Requester`. (#3)
- Banner and a code-coverage badge.

**Full changelog:** https://github.com/Baspa/filament-canary/compare/v1.0.0...v1.1.0
