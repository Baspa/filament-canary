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
     | access needs a specific user (a role, a flag):
     |
     |   'acting_as' => fn (\Filament\Panel $panel) => User::factory()->admin()->create(),
     |
     | Or per panel:
     |
     |   'acting_as' => [
     |       'admin'   => fn ($panel) => User::factory()->admin()->create(),
     |       'default' => fn ($panel) => User::factory()->create(),
     |   ],
     */
    'acting_as' => null,

    /*
     |--------------------------------------------------------------------------
     | Tenant resolver
     |--------------------------------------------------------------------------
     | For tenant-aware panels, how to build the tenant used in URLs. Defaults to the
     | tenant-model factory. Accepts a closure or a per-panel array, like `acting_as`.
     |
     |   'tenant' => fn (\Filament\Panel $panel) => Team::factory()->create(),
     */
    'tenant' => null,

];
