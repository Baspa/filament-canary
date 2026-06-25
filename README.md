# Filament Canary

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)

A runtime smoke-sweep for your Filament panels. It introspects every panel, resource and page at runtime and asserts the one thing you almost never write tests for: **every page mounts for an authorized user, and is denied to guests.** No generated files, no drift — new resources are covered automatically, and it runs in CI on every PR.

A canary in the coal mine: if a page starts throwing a 500 or quietly leaks access, the sweep falls over before your users do.

## Why this instead of generating test files?

Because generated tests rot. Filament is *introspectable* — `Filament::getPanels()` → resources → pages — so instead of writing a file per resource that you have to maintain (and that goes stale the moment you add a resource), Canary walks the live route table each run. One sweep covers the whole panel, today and after every refactor.

It is deliberately **narrow**: it proves pages *mount and authorize*. It does not submit forms or assert CRUD — that's the genuinely app-specific part you should write by hand.

## Why not just ask AI to generate the tests?

You can — and for the app-specific parts (form validation, business rules) you probably should. But "every page still mounts and authorizes" is a *standing guarantee*, not a one-time task, and that's a poor fit for one-shot generation:

- **AI writes tests once; Canary enforces forever.** Add a resource next month and AI doesn't know. Canary's sweep already covers it and fails CI if it breaks.
- **Deterministic and reviewable.** The same sweep runs identically on every machine and every PR — no per-developer variation, no tokens spent, no prompt to babysit.
- **Zero files to maintain.** AI-generated test files are still files: they drift against Filament upgrades and renamed resources. Canary holds none — it reads the live panel.
- **It's a drift detector, not just a test.** The moment a new page isn't covered or starts throwing, the same mechanism that provides coverage is the thing that fails the build.

Think of it as infrastructure (a health check over your whole panel) rather than a batch of generated tests. Use AI for the deep, bespoke tests; let Canary hold the line on "nothing is silently broken or unprotected."

## Installation

```bash
composer require baspa/filament-canary --dev
```

Then let Canary inspect your panels and propose a config for you:

```bash
php artisan canary:install
```

It reads each panel's access gate (the user model's `canAccessPanel`, plus tenancy) and proposes an `acting_as` resolver — `assignRole('admin')` for role gates, a matching email for allowlists, a factory flag for boolean gates, or a plain factory user when it can't tell (clearly marked, for you to adjust). After you confirm, it writes `config/filament-canary.php` with the proposed closures baked in. Decline, and it just prints the snippet to paste.

Prefer to do it by hand? Publish the config and edit it yourself:

```bash
php artisan vendor:publish --tag="filament-canary-config"
```

## Usage

### In your test suite (recommended)

```php
use Baspa\FilamentCanary\Testing\InteractsWithCanary;

uses(InteractsWithCanary::class);

it('every Filament page mounts and authorizes', function () {
    $this->canarySweep();
});
```

`canarySweep()` runs the whole sweep and fails the test if any page returned a server error or leaked access to a guest. It returns every result, so you can make extra assertions if you want.

### As a CI command

```bash
php artisan canary:check
```

Prints a per-page report and exits non-zero when there are hard failures:

```
  ✅  admin   filament.admin.resources.posts.index    authorized: 200, guest: 302
  ✅  admin   filament.admin.resources.posts.edit     authorized: 200, guest: 302
  🔒  admin   filament.admin.resources.audits.index   authorized user was denied (403); configure filament-canary.acting_as
  ⏭️  admin   filament.admin.resources.tags.edit      model [App\Models\Tag] has no factory; cannot build a record
  ❌  admin   filament.admin.resources.orders.index   authorized request returned 500

  ✅ 12 passed   ❌ 1 failed   🔒 1 needs-auth   ⏭️ 2 skipped
```

Pass `--strict` to also fail on `needs-auth` pages once you've wired up `acting_as`.

## How a page is judged

| Result | Meaning |
| --- | --- |
| ✅ **passed** | Authorized user got a 2xx **and** a guest was denied (redirect / 401 / 403). |
| ❌ **failed** | Server error (5xx), or a guest reached the page (authorization leak). The canary's whole reason for existing. |
| 🔒 **needs-auth** | The resolved user couldn't reach the page (401/403/redirect). Almost always means you need to configure `acting_as`. Not a hard failure unless `--strict`. |
| ⏭️ **skipped** | Couldn't be swept automatically — no model factory for a record page, a tenant that needs a resolver, or route parameters Canary can't fill. Always shown with a reason; nothing is silently left untested. |

## Configuration

By default Canary creates an authorized user via your guard's provider-model factory. When panel access needs a specific user (a role, a flag), give it one:

```php
// config/filament-canary.php
'acting_as' => fn (\Filament\Panel $panel) => \App\Models\User::factory()->admin()->create(),
```

Per panel:

```php
'acting_as' => [
    'admin'   => fn ($panel) => \App\Models\User::factory()->admin()->create(),
    'default' => fn ($panel) => \App\Models\User::factory()->create(),
],
```

Tenant-aware panels resolve a tenant the same way (defaults to the tenant-model factory):

```php
'tenant' => fn (\Filament\Panel $panel) => \App\Models\Team::factory()->create(),
```

Other options: `panels.only` / `panels.except`, `exclude` (resource/page classes), `test_guests`, `strict_authorization`. See the published config for details.

## What's out of scope (on purpose)

- Form submissions / full CRUD assertions — that's the app-specific part to write by hand.
- Filament v3 (this targets v4 and v5).
- Non-Filament Laravel routes.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
