<p align="center">
  <img src="art/banner.png" alt="Filament Canary" width="100%">
</p>

# Filament Canary

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/phpstan.yml)
[![Code Coverage](https://img.shields.io/codecov/c/github/baspa/filament-canary?style=flat-square)](https://codecov.io/gh/baspa/filament-canary)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)

A runtime smoke-sweep for your Filament panels. It introspects every panel, resource and page at runtime and asserts the one thing you almost never write tests for: **every page mounts for an authorized user, and is denied to guests.** No generated files, no drift — new resources are covered automatically, and it runs in CI on every PR.

A canary in the coal mine: if a page starts throwing a 500 or quietly leaks access, the sweep falls over before your users do.

## Why this instead of generating test files?

Because generated tests rot. Filament is *introspectable* — `Filament::getPanels()` → resources → pages — so instead of writing a file per resource that you have to maintain (and that goes stale the moment you add a resource), Canary walks the live route table each run. One sweep covers the whole panel, today and after every refactor.

It is deliberately **narrow**: it proves pages *mount and authorize*. It does not submit forms or assert CRUD — that's the genuinely app-specific part you should write by hand.

## Why not just ask AI to generate the tests?

You can — an AI agent can even keep generated tests up to date as you build. For the app-specific parts (form validation, business rules) that's a great use of it. The difference isn't "AI can't keep up." It's that "every page still mounts and authorizes" is a *standing guarantee* that shouldn't depend on someone remembering to ask:

- **It runs with no AI in the loop.** A junior adding a resource, a Dependabot PR bumping Filament, a teammate without an agent, CI itself — they all get the coverage for free. No "don't forget to regenerate the tests."
- **No per-change review step.** AI-updated tests have to be re-read every time — did it cover the new page, did it pass for the right reason? The sweep is reviewed once; new pages flow through it automatically.
- **The failure mode is the exception.** It breaks precisely the one time you *don't* ask — a hotfix, a rename buried in a big diff, someone else's PR. The sweep has no "remember to" step.
- **Zero generated files.** Even AI-maintained test files are files that can half-update or conflict. Canary holds none — it reads the live panel each run.

So it's not AI vs. Canary. Use AI for the deep, bespoke tests; let Canary make the one mechanical guarantee — "every page loads and authorizes" — *structural* instead of dependent on a human-plus-agent getting it right every single time.

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

Other options: `panels.only` / `panels.except`, `exclude` (resource/page classes), `test_guests`, `strict_authorization`, `use_transaction`. See the published config for details.

## Safety

The sweep creates users and records via factories and requests every page as an
authenticated user, so it takes two precautions:

- **It runs in a database transaction and rolls it back** — nothing it creates is
  persisted. (Covers the default connection; disable with `use_transaction => false`.)
- **It refuses to run in the `production` environment** — a rollback can't undo the other
  side effects of hitting every page as an admin (queued jobs, mail, audit logs, writes on
  other connections), so Canary simply won't run there.

## What's out of scope (on purpose)

- Form submissions / full CRUD assertions — that's the app-specific part to write by hand.
- Filament v3 (this targets v4 and v5).
- Non-Filament Laravel routes.

## Non-standard authentication

By default Canary authenticates each request by setting the resolved user on the panel's
guard. That covers the common case (session/Eloquent guards). It does **not** fit panels
whose access depends on custom state Canary can't see — for example a custom API/SSO guard,
or a `canAccessPanel()` that checks **session data** rather than the user itself:

```php
public function canAccessPanel(Panel $panel): bool
{
    return session()->has('user') && session()->has('loginToken'); // Canary can't satisfy this by default
}
```

For these, swap the requester. `Baspa\FilamentCanary\Sweep\Requester` is a one-method
interface bound in the container, so you can bind your own implementation that establishes
auth however your app needs it (seed the session, mint an API token, etc.):

```php
// e.g. in a test's setUp() or a service provider
use Baspa\FilamentCanary\Sweep\Requester;

$this->app->bind(Requester::class, MyRequester::class);
```

```php
use Baspa\FilamentCanary\Sweep\Requester;
use Illuminate\Contracts\Auth\Authenticatable;

class MyRequester implements Requester
{
    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        // establish whatever state the panel's gate requires, then return the status code
    }
}
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
