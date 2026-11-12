<?php

// config for Baspa/FilamentCanary
return [

    /*
     |--------------------------------------------------------------------------
     | Panels
     |--------------------------------------------------------------------------
     | Limit the sweep to specific panels. Leave `only` empty to sweep them all.
     */
    'panels' => [
        'only' => [],
        'except' => [],
    ],

    /*
     |--------------------------------------------------------------------------
     | Excluded resources / pages
     |--------------------------------------------------------------------------
     | Fully-qualified Resource or Page class names to skip entirely.
     */
    'exclude' => [],

    /*
     |--------------------------------------------------------------------------
     | Guest checks
     |--------------------------------------------------------------------------
     | When true, every page is also requested without authentication and must be
     | denied (redirect / 401 / 403). A guest 2xx is reported as an authorization leak.
     */
    'test_guests' => true,

    /*
     |--------------------------------------------------------------------------
     | Fail on skipped pages
     |--------------------------------------------------------------------------
     | When true, pages canary could not sweep (skipped) count as failures, so a
     | missing factory or resolver can't leave a page untested. `canary:check
     | --fail-on-skipped` does the same for one run. A sweep that found no pages,
     | or where no page passed, always fails.
     */
    'fail_on_skipped' => false,

    /*
     |--------------------------------------------------------------------------
     | Database transaction
     |--------------------------------------------------------------------------
     | The sweep creates users/records via factories. With this on, the whole run
     | is wrapped in a transaction and rolled back, so nothing is persisted. Only
     | the default connection is covered; canary always refuses to run in the
     | production environment regardless of this setting.
     */
    'use_transaction' => true,

    /*
     |--------------------------------------------------------------------------
     | Strict authorization
     |--------------------------------------------------------------------------
     | When true, pages the resolved user could not reach (needs-auth) count as
     | failures. Keep false while wiring up `acting_as`, then ratchet to true in CI.
     */
    'strict_authorization' => false,

    /*
     |--------------------------------------------------------------------------
     | Authorized user resolver
     |--------------------------------------------------------------------------
     | How to build the user that should be able to reach a panel's pages. By default
     | canary creates one via the guard's provider-model factory. Override when panel
     | access needs a specific user (a role, a flag) with an invokable class: it gets
     | the panel and returns the user. `php artisan canary:install` generates one in
     | tests/Canary. Only the class name is stored here, so production doesn't need it.
     |
     |   'acting_as' => \Tests\Canary\AdminActingAs::class,
     |
     |   class AdminActingAs
     |   {
     |       public function __invoke(Panel $panel): Authenticatable
     |       {
     |           return User::factory()->admin()->create();
     |       }
     |   }
     |
     | Or per panel, with an optional fallback for the other panels:
     |
     |   'acting_as' => [
     |       'admin'   => \Tests\Canary\AdminActingAs::class,
     |       'default' => \Tests\Canary\DefaultActingAs::class,
     |   ],
     |
     | A closure (fn (Panel $panel) => ...) works too, but only from a test via
     | config()->set(): a closure in this file breaks `php artisan config:cache`.
     */
    'acting_as' => null,

    /*
     |--------------------------------------------------------------------------
     | Tenant resolver
     |--------------------------------------------------------------------------
     | For tenant-aware panels, how to build the tenant used in URLs. By default canary
     | creates one with the tenant-model factory and attaches the acting user through
     | the tenant's many-to-many relation to the user model: `members()`, `users()` or
     | the user model's plural (`teamMembers()` for TeamMember). Configure a resolver
     | when there is no such relation, or when access depends on more. It accepts the
     | same values as `acting_as` and gets the panel and the acting user:
     |
     |   'tenant' => \Tests\Canary\AdminTenant::class,
     |
     |   class AdminTenant
     |   {
     |       public function __invoke(Panel $panel, Authenticatable $user): Model
     |       {
     |           return Team::factory()->hasAttached($user, ['role' => 'owner'], 'members')->create();
     |       }
     |   }
     */
    'tenant' => null,

];
