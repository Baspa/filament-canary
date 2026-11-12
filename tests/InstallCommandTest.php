<?php

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Models\RoleUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

// In testbench, config_path() and base_path() point into the shared vendor skeleton.
function removeCanaryInstallOutput(): void
{
    File::delete([config_path('filament-canary.php'), app()->getCachedConfigPath()]);
    File::deleteDirectory(base_path('tests/Canary'));
}

beforeEach(fn () => removeCanaryInstallOutput());
afterEach(fn () => removeCanaryInstallOutput());

it('writes a config that config:cache accepts', function () {
    config()->set('auth.providers.users.model', RoleUser::class);
    config()->set('filament-canary.panels.only', ['admin', 'team']);

    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'yes')
        ->assertExitCode(0);

    expect(File::files(base_path('tests/Canary')))->toHaveCount(3)
        ->and(file_get_contents(config_path('filament-canary.php')))
        ->toContain("'admin' => \\Tests\\Canary\\AdminActingAs::class,")
        ->toContain("'team' => \\Tests\\Canary\\TeamTenant::class,")
        ->toContain("'use_transaction' => true,")
        ->toContain('| Strict authorization');

    expect(Artisan::call('config:cache'))->toBe(0);

    $cached = require app()->getCachedConfigPath();

    expect($cached['filament-canary']['acting_as'])->toBe([
        'admin' => 'Tests\\Canary\\AdminActingAs',
        'team' => 'Tests\\Canary\\TeamActingAs',
    ]);
});

it('generates a tenant resolver that gets the team panel through', function () {
    config()->set('auth.providers.users.model', TeamMember::class);
    config()->set('filament-canary.panels.only', ['team']);

    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'yes')
        ->assertExitCode(0);

    require_once base_path('tests/Canary/TeamTenant.php');
    config()->set('filament-canary.tenant', (require config_path('filament-canary.php'))['tenant']);

    $tenantScoped = array_filter(
        app(SmokeSweep::class)->run(),
        fn (SweepResult $result) => $result->target->tenantScoped,
    );

    expect($tenantScoped)->not->toBeEmpty();

    foreach ($tenantScoped as $result) {
        expect($result->status)->toBe(SweepStatus::Passed, "{$result->target->routeName}: {$result->reason}");
    }
});

it('writes nothing when declined and prints what it would have written', function () {
    config()->set('auth.providers.users.model', RoleUser::class);

    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'no')
        ->expectsOutputToContain('tests/Canary/AdminActingAs.php')
        ->expectsOutputToContain('class AdminActingAs')
        ->expectsOutputToContain("'admin' => \\Tests\\Canary\\AdminActingAs::class,")
        ->assertExitCode(0);

    expect(config_path('filament-canary.php'))->not->toBeFile()
        ->and(base_path('tests/Canary'))->not->toBeDirectory();
});

it('keeps an existing resolver class unless overwriting is confirmed', function (string $answer, bool $overwritten) {
    config()->set('auth.providers.users.model', RoleUser::class);

    $path = base_path('tests/Canary/AdminActingAs.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php // edited by hand');

    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'yes')
        ->expectsConfirmation('tests/Canary/AdminActingAs.php already exists. Overwrite it?', $answer)
        ->assertExitCode(0);

    expect(file_get_contents($path) !== '<?php // edited by hand')->toBe($overwritten)
        ->and(file_get_contents(config_path('filament-canary.php')))
        ->toContain("'admin' => \\Tests\\Canary\\AdminActingAs::class,");
})->with([
    'declined' => ['no', false],
    'confirmed' => ['yes', true],
]);

it('only analyzes the panels selected by panels.only and panels.except', function () {
    config()->set('auth.providers.users.model', RoleUser::class);
    config()->set('filament-canary.panels', ['only' => ['admin', 'team'], 'except' => ['admin']]);

    $this->artisan('canary:install')
        ->expectsConfirmation('Write these proposals to config/filament-canary.php?', 'no')
        ->expectsOutputToContain('class TeamActingAs')
        ->doesntExpectOutputToContain('class AdminActingAs')
        ->assertExitCode(0);
});
