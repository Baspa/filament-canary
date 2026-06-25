<?php

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;

/**
 * @return array<string, SweepResult> keyed by route name
 */
function sweepByRoute(): array
{
    $results = app(SmokeSweep::class)->run();

    return collect($results)->keyBy(fn (SweepResult $r) => $r->target->routeName)->all();
}

it('discovers and passes ordinary resource and dashboard pages for an authorized user', function () {
    $byRoute = sweepByRoute();

    $passing = [
        'filament.admin.pages.dashboard',
        'filament.admin.resources.posts.index',
        'filament.admin.resources.posts.create',
        'filament.admin.resources.posts.view',
        'filament.admin.resources.posts.edit',
        'filament.admin.resources.tags.index',
    ];

    foreach ($passing as $route) {
        expect($byRoute)->toHaveKey($route);
        expect($byRoute[$route]->status)->toBe(SweepStatus::Passed, "expected {$route} to pass, got: ".($byRoute[$route]->reason ?? ''));
        expect($byRoute[$route]->authorizedStatus)->toBe(200);
    }
});

it('skips record-bound pages whose model has no factory, with a reason', function () {
    $result = sweepByRoute()['filament.admin.resources.tags.edit'] ?? null;

    expect($result)->not->toBeNull();
    expect($result->status)->toBe(SweepStatus::Skipped);
    expect($result->reason)->toContain('factory');
});

it('reports pages the authorized user cannot reach as needs-auth, not failure', function () {
    $result = sweepByRoute()['filament.admin.resources.secrets.index'] ?? null;

    expect($result)->not->toBeNull();
    expect($result->status)->toBe(SweepStatus::NeedsAuth);
    expect($result->authorizedStatus)->toBe(403);
});

it('denies guests on every page that passed', function () {
    foreach (sweepByRoute() as $result) {
        if ($result->status === SweepStatus::Passed) {
            expect($result->guestStatus)->toBeGreaterThanOrEqual(300, "guest was not denied on {$result->target->routeName}");
        }
    }
});

it('has no hard failures across the fixture panel', function () {
    $failures = array_filter(sweepByRoute(), fn (SweepResult $r) => $r->status === SweepStatus::Failed);

    expect($failures)->toBe([]);
});

it('passes the InteractsWithCanary assertion helper', function () {
    $this->canarySweep();
});
