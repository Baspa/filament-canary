<?php

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantTeam;
use Baspa\FilamentCanary\Tests\Fixtures\Resolvers\AttachingTeamTenant;
use Baspa\FilamentCanary\Tests\Fixtures\Resolvers\OneArgumentTeamTenant;
use Baspa\FilamentCanary\Tests\Fixtures\Resolvers\TeamMemberActingAs;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function () {
    config()->set('filament-canary.panels.only', ['team']);
    config()->set('auth.providers.users.model', TeamMember::class);
});

/**
 * @return list<SweepResult>
 */
function sweepTeamTenantPages(): array
{
    $results = array_filter(
        app(SmokeSweep::class)->run(),
        fn (SweepResult $result) => $result->target->tenantScoped,
    );

    expect($results)->not->toBeEmpty();

    return array_values($results);
}

function expectEveryResultPassed(array $results): void
{
    foreach ($results as $result) {
        expect($result->status)->toBe(
            SweepStatus::Passed,
            "expected {$result->target->routeName} to pass, got: ".($result->reason ?? $result->status->value),
        );
    }
}

it('passes every tenant page with a tenant resolver that attaches the acting user', function () {
    config()->set('filament-canary.tenant', fn (Panel $panel, Authenticatable $user) => TenantTeam::factory()->hasAttached($user, [], 'members')->create());

    expectEveryResultPassed(sweepTeamTenantPages());
});

it('passes every tenant page with invokable resolver classes in a per-panel array', function () {
    config()->set('filament-canary.acting_as', ['default' => TeamMemberActingAs::class]);
    config()->set('filament-canary.tenant', ['team' => AttachingTeamTenant::class]);

    expectEveryResultPassed(sweepTeamTenantPages());
});

it('still accepts a tenant closure that only takes the panel', function () {
    config()->set('filament-canary.tenant', function (Panel $panel) {
        $team = TenantTeam::factory()->create();
        $team->members()->attach(TeamMember::query()->pluck('id'));

        return $team;
    });

    expectEveryResultPassed(sweepTeamTenantPages());
});

it('still accepts an invokable tenant class that only takes the panel', function () {
    config()->set('filament-canary.tenant', OneArgumentTeamTenant::class);

    expectEveryResultPassed(sweepTeamTenantPages());
});

it('resolves the acting user before the tenant, once per panel', function () {
    $calls = [];

    config()->set('filament-canary.acting_as', function (Panel $panel) use (&$calls) {
        $calls[] = 'user';

        return TeamMember::factory()->create();
    });

    config()->set('filament-canary.tenant', function (Panel $panel, Authenticatable $user) use (&$calls) {
        $calls[] = 'tenant';

        return TenantTeam::factory()->hasAttached($user, [], 'members')->create();
    });

    sweepTeamTenantPages();

    expect($calls)->toBe(['user', 'tenant']);
});

it('creates one default tenant per panel, not one per page', function () {
    config()->set('filament-canary.use_transaction', false);

    $pages = count(sweepTeamTenantPages());

    expect($pages)->toBeGreaterThan(1)
        ->and(TenantTeam::query()->count())->toBe(1);
});

it('runs a failing tenant resolver once and skips every tenant page with its reason', function () {
    $calls = 0;

    config()->set('filament-canary.tenant', function () use (&$calls) {
        $calls++;

        throw new RuntimeException('no teams today');
    });

    $results = sweepTeamTenantPages();

    expect($calls)->toBe(1);

    foreach ($results as $result) {
        expect($result->status)->toBe(SweepStatus::Skipped)
            ->and($result->reason)->toBe('could not create a tenant for panel [team]: no teams today');
    }
});
