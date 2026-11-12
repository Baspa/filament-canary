# Changelog

All notable changes to `baspa/filament-canary` will be documented in this file.

## v1.4.0 - 2026-11-12

### Upgrading

- **The sweep only runs in allowed environments.** The new `allowed_environments` option defaults to `['local', 'testing']`. `production`, `prod`, `prd` and `live` are always refused.
  
  - If you run `canary:check` with another `APP_ENV` (for example `ci` or `staging`), add it: `'allowed_environments' => ['local', 'testing', 'ci']`. Wildcards such as `ci-*` work.
  - When the environment is refused, `canary:check` prints why and exits 1.
  
  (#20, #36)
  

### Fixed

- **Every swept request starts with a clean state, and your test gets its own state back afterwards.**
  
  - The session, the default guard and its user resolver, Laravel's redirector, and Filament's current panel and tenant are restored after each request.
  - Running the sweep twice, or `canarySweep()` and then `canary:check` in one test, now gives the same results.
  - On Filament 4 / Livewire 3, a page that aborted in `mount()` no longer made every later guest check return 500.
  - Swept requests no longer see session data your test set up. For panels whose access depends on session data, bind your own `Requester` (see the README).
  
  (#33, #36)
  
- **Invalid config lists fail loudly.** `panels.only`, `panels.except` and `exclude` convert numbers and stringable values to strings. Anything else is rejected with an error that names the key, so a malformed `panels.only` can no longer silently widen the sweep to every panel. (#22, #35)
  
- **`canary:install` no longer crashes** when a guard's provider or a provider's model isn't a string. (#22, #35)
  

### Changed

- **PHPStan level:** PHPStan now runs at level max. (#22, #35)
  
- **Skeleton leftovers removed:**
  
  - the unused Dependabot auto-merge workflow and FUNDING file;
  - placeholder text in the changelog and issue templates;
  - three unused dev dependencies.
  
  Blank issues are now allowed. (#23, #34)
  
- **Internal API:**
  
  - `KernelRequester`'s constructor now takes the auth manager, the session and the container.
  - `AccessProposal` no longer has `guard` and `userModel` fields.
  - Code that only implements the `Requester` interface is not affected.
  

**Full changelog:** https://github.com/Baspa/filament-canary/compare/v1.3.0...v1.4.0

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
