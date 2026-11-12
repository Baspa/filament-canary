<p align="center">
  <img src="art/banner.jpg" alt="Filament Canary" width="100%">
</p>

# Filament Canary

[![Latest Version on Packagist](https://img.shields.io/packagist/v/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)
[![Tests](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/baspa/filament-canary/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/baspa/filament-canary/actions/workflows/phpstan.yml)
[![Code Coverage](https://img.shields.io/codecov/c/github/baspa/filament-canary?style=flat-square)](https://codecov.io/gh/baspa/filament-canary)
[![Total Downloads](https://img.shields.io/packagist/dt/baspa/filament-canary.svg?style=flat-square)](https://packagist.org/packages/baspa/filament-canary)

A runtime smoke-sweep for your Filament panels. It introspects every panel, resource and page at runtime and asserts the one thing you almost never write tests for: **every page mounts for an authorized user, and is denied to guests.** No generated test files, no drift — new resources are covered automatically, and it runs in CI on every PR.

A canary in the coal mine: if a page starts throwing a 500 or quietly leaks access, the sweep falls over before your users do.

## Why this instead of generating test files?

Because generated tests rot. Filament is *introspectable* — `Filament::getPanels()` → resources → pages — so instead of writing a file per resource that you have to maintain (and that goes stale the moment you add a resource), Canary walks the live route table each run. One sweep covers the whole panel, today and after every refactor.

It is deliberately **narrow**: it proves pages *mount and authorize*. It does not submit forms or assert CRUD — that's the genuinely app-specific part you should write by hand.

The same goes for tests an AI agent writes or keeps up to date: someone still has to ask for them and review them. The sweep needs neither, so a teammate's PR, a Filament upgrade or a hotfix is covered without anyone remembering to regenerate anything.

## Installation

Requires PHP 8.3+, Laravel 12 or 13, and Filament 4 or 5.

```bash
composer require baspa/filament-canary --dev
```

Then let Canary inspect your panels and propose a config for you:

```bash
php artisan canary:install
```

It reads each panel's access gate (the user model's `canAccessPanel`, plus tenancy) and proposes an `acting_as` resolver: `assignRole('admin')` for role gates (when the user model has `assignRole()`), a matching email for allowlists, a factory flag for boolean gates, or a plain factory user when it can't tell (clearly marked, for you to adjust). For a tenant panel it also proposes a `tenant` resolver that makes the acting user a member, when it finds the membership relation.

After you confirm, it writes each resolver as an invokable class in `tests/Canary/` and puts the class names in `config/filament-canary.php`. It asks before overwriting an existing file, and an overwritten config keeps your other settings. Decline, and nothing is written: it prints the classes and the config entries instead.

Prefer to do it by hand? Publish the config and edit it yourself:

```bash
php artisan vendor:publish --tag="filament-canary-config"
```

## Usage

### In your test suite (recommended)

```php
use Baspa\FilamentCanary\Testing\InteractsWithCanary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class, InteractsWithCanary::class);

it('every Filament page mounts and authorizes', function () {
    $this->canarySweep();
});
```

`canarySweep()` runs the whole sweep and fails the test when the sweep fails (see [How a page is judged](#how-a-page-is-judged)). It returns every result, so you can make extra assertions if you want. `RefreshDatabase` gives the sweep a migrated database; leave it out of `uses()` if your `Pest.php` already applies it.

### As a CI command

```bash
php artisan canary:check
```

Prints a per-page report and exits non-zero when the sweep fails:

```
+----+-------+----------------------------------------------+-----------------------------------------------------------------------------------------+
|    | Panel | Page                                         | Detail                                                                                  |
+----+-------+----------------------------------------------+-----------------------------------------------------------------------------------------+
| ✅ | admin | filament.admin.pages.dashboard               | authorized: 200, guest: 302                                                             |
| ✅ | admin | filament.admin.resources.posts.index         | authorized: 200, guest: 302                                                             |
| ✅ | admin | filament.admin.resources.posts.create        | authorized: 200, guest: 302                                                             |
| ✅ | admin | filament.admin.resources.posts.view          | authorized: 200, guest: 302                                                             |
| ✅ | admin | filament.admin.resources.posts.edit          | authorized: 200, guest: 302                                                             |
| ✅ | admin | filament.admin.resources.tags.index          | authorized: 200, guest: 302                                                             |
| ⏭️ | admin | filament.admin.resources.tags.edit           | model […\Models\Tag] has no factory; cannot build a record for this page                |
| 🔒 | admin | filament.admin.resources.secrets.index       | authorized user was denied (403); configure filament-canary.acting_as for panel [admin] |
| ❌ | lazy  | filament.lazy.pages.dashboard                | authorized request returned 500                                                         |
| ❌ | lazy  | filament.lazy.resources.deferred-posts.index | authorized request returned 500                                                         |
+----+-------+----------------------------------------------+-----------------------------------------------------------------------------------------+

✅ 6 passed   ❌ 2 failed   🔒 1 needs-auth   ⏭️ 1 skipped
```

Pass `--strict` to also fail on `needs-auth` pages once you've wired up `acting_as`, and `--fail-on-skipped` to fail on skipped pages. To make either permanent, and to apply it to `canarySweep()` as well, set `strict_authorization` or `fail_on_skipped` in the config.

`canary:check` uses the app's configured database and doesn't run migrations. Against an unmigrated database every page is skipped, so the sweep fails. Migrate a file-based test database first; `:memory:` won't work, because `migrate` and `canary:check` run in separate processes. In GitHub Actions:

```yaml
- name: Canary sweep
  env:
    APP_ENV: testing
    DB_CONNECTION: sqlite
    DB_DATABASE: ${{ github.workspace }}/database/canary.sqlite
  run: |
    cp .env.example .env
    php artisan key:generate
    php artisan migrate --force
    php artisan canary:check
```

If your tests already run in CI, prefer the test-suite route: `RefreshDatabase` migrates for you.

## How a page is judged

Canary sweeps every GET page in each panel: the dashboard, custom pages, resource pages and the profile page. It skips the routes in the panel's `auth` group (login, registration, password reset, email verification and the like) and Filament's redirect-only `home` and `tenant` routes.

Each page is requested as the authorized user and, when `test_guests` is on, as a guest. Lazy widgets, lazy relation managers and deferred tables render during that request, so an error in them fails the page too.

| Result | Meaning |
| --- | --- |
| ✅ **passed** | Authorized user got a 2xx **and** a guest was denied (redirect / 401 / 403). |
| ❌ **failed** | The authorized user got a 5xx, or a 4xx other than 401/403 (except the 404 under skipped). Or a guest got a 2xx (an authorization leak), or any other status that isn't a redirect, 401 or 403, such as a 404 or a 5xx. The canary's whole reason for existing. |
| 🔒 **needs-auth** | The resolved user couldn't reach the page (redirect / 401 / 403). Almost always means you need to configure `acting_as`. Fails the sweep only in strict mode. |
| ⏭️ **skipped** | Couldn't be swept automatically: no model factory for a record page, a resolver that failed, route parameters Canary can't fill, or a 404 on a record or tenant page (the reason names the likely cause). Always shown with a reason. Fails the sweep only with `fail_on_skipped`. |

The sweep as a whole also fails when it found no pages, or when no page passed: a run where everything was skipped proves nothing. `canary:check` and `canarySweep()` apply the same rules.

## Configuration

By default Canary creates the authorized user with your guard's provider-model factory. When panel access needs a specific user (a role, a flag), point `acting_as` at an invokable class. It gets the panel and returns the user:

```php
// config/filament-canary.php
'acting_as' => \Tests\Canary\AdminActingAs::class,
```

```php
// tests/Canary/AdminActingAs.php
namespace Tests\Canary;

use App\Models\User;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminActingAs
{
    public function __invoke(Panel $panel): Authenticatable
    {
        return User::factory()->admin()->create();
    }
}
```

The config only holds the class name, so production never needs the class.

Per panel, with an optional `default` for the other panels:

```php
'acting_as' => [
    'admin' => \Tests\Canary\AdminActingAs::class,
    'default' => \Tests\Canary\DefaultActingAs::class,
],
```

A panel without an entry gets the factory user.

Tenant-aware panels also need a tenant. By default Canary creates one with the tenant model's factory, and attaches the acting user when the tenant model has a many-to-many `members()` or `users()` relation (or one named after the user model). When membership needs more, such as a pivot value, set a `tenant` resolver. It takes the same values as `acting_as`, and gets the panel and the acting user, which Canary resolves first:

```php
// config/filament-canary.php
'tenant' => \Tests\Canary\TeamTenant::class,
```

```php
// tests/Canary/TeamTenant.php
namespace Tests\Canary;

use App\Models\Team;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TeamTenant
{
    public function __invoke(Panel $panel, Authenticatable $user): Model
    {
        return Team::factory()->hasAttached($user, ['role' => 'owner'], 'members')->create();
    }
}
```

Use class names, not closures, in `config/filament-canary.php`: Laravel can't serialize a closure, so `php artisan config:cache` fails. In a test, a closure works through `config()->set()`:

```php
use App\Models\User;
use Filament\Panel;

config()->set('filament-canary.acting_as', fn (Panel $panel) => User::factory()->admin()->create());
```

Other options: `panels.only` / `panels.except` pick the panels to sweep. `exclude` takes Resource classes (all of their pages) and Page classes (that page only, such as `Dashboard::class` or `EditProfile::class`), and applies to every panel. `test_guests`, `strict_authorization`, `fail_on_skipped` and `use_transaction` are explained in the published config.

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
