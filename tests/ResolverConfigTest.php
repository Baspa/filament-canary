<?php

use Baspa\FilamentCanary\Support\ConfiguredResolver;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Baspa\FilamentCanary\Tests\Fixtures\Models\RoleUser;
use Baspa\FilamentCanary\Tests\Fixtures\Resolvers\TeamMemberActingAs;
use Filament\Panel;

function resolverConfigPanel(): Panel
{
    return Panel::make()->id('admin');
}

it('reads a closure, an invokable class name, or a per-panel array of those', function (mixed $value, ?string $expected) {
    config()->set('filament-canary.acting_as', $value);

    $resolver = ConfiguredResolver::find('filament-canary.acting_as', resolverConfigPanel());

    expect($resolver === null ? null : get_debug_type($resolver))->toBe($expected);
})->with([
    'nothing' => [null, null],
    'closure' => [fn () => null, Closure::class],
    'invokable class' => [TeamMemberActingAs::class, TeamMemberActingAs::class],
    'panel entry' => [['admin' => TeamMemberActingAs::class, 'default' => fn () => null], TeamMemberActingAs::class],
    'default entry' => [['shop' => fn () => null, 'default' => TeamMemberActingAs::class], TeamMemberActingAs::class],
    'no entry for the panel' => [['shop' => TeamMemberActingAs::class], null],
]);

it('rejects a value that is neither callable nor an invokable class', function () {
    config()->set('filament-canary.acting_as', ['admin' => Post::class]);

    ConfiguredResolver::find('filament-canary.acting_as', resolverConfigPanel());
})->throws(InvalidArgumentException::class, 'got ['.Post::class.']');

it('uses an invokable acting_as class from config', function () {
    // RoleUser has no factory, so a passing page proves the configured class made the user.
    config()->set('auth.providers.users.model', RoleUser::class);
    config()->set('filament-canary.acting_as', TeamMemberActingAs::class);

    $dashboard = collect(app(SmokeSweep::class)->run())
        ->first(fn (SweepResult $result) => $result->target->routeName === 'filament.admin.pages.dashboard');

    expect($dashboard->status)->toBe(SweepStatus::Passed);
});

it('runs an acting_as resolver that returns no user once per panel', function () {
    config()->set('auth.providers.users.model', RoleUser::class);

    $calls = 0;
    config()->set('filament-canary.acting_as', function () use (&$calls) {
        $calls++;

        return null;
    });

    $results = app(SmokeSweep::class)->run();

    expect(count($results))->toBeGreaterThan(1)
        ->and($calls)->toBe(1);

    foreach ($results as $result) {
        expect($result->status)->toBe(SweepStatus::Skipped)
            ->and($result->reason)->toBe('no authorized user; configure filament-canary.acting_as for panel [admin] (guard [web])');
    }
});

it('runs a throwing acting_as resolver once per panel and reuses its reason', function () {
    $calls = 0;
    config()->set('filament-canary.acting_as', function () use (&$calls) {
        $calls++;

        throw new RuntimeException('no users today');
    });

    $results = app(SmokeSweep::class)->run();

    expect(count($results))->toBeGreaterThan(1)
        ->and($calls)->toBe(1);

    foreach ($results as $result) {
        expect($result->status)->toBe(SweepStatus::Skipped)
            ->and($result->reason)->toBe('could not create an authorized user for panel [admin]: no users today');
    }
});

it('skips a panel whose acting_as config is not a resolver, saying why', function () {
    config()->set('filament-canary.acting_as', 'NotAClass');

    $result = app(SmokeSweep::class)->run()[0];

    expect($result->status)->toBe(SweepStatus::Skipped)
        ->and($result->reason)->toContain('[filament-canary.acting_as] for panel [admin] must be a callable or an invokable class name, got [NotAClass]');
});
