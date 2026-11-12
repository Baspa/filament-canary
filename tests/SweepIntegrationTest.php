<?php

use Baspa\FilamentCanary\Introspection\PanelCollector;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Baspa\FilamentCanary\Tests\Fixtures\Models\User;

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

it('rolls back every user and record it creates', function () {
    app(SmokeSweep::class)->run();

    expect(User::count())->toBe(0)
        ->and(Post::count())->toBe(0);
});

it('reads numbers and Stringable objects in panels.only as panel ids', function () {
    $panels = new class extends PanelCollector
    {
        /** @var list<string> */
        public array $only = [];

        public function collect(array $only = [], array $except = []): array
        {
            $this->only = $only;

            return [];
        }
    };
    app()->instance(PanelCollector::class, $panels);
    config()->set('filament-canary.panels.only', ['admin', 1, 2.5, str('team')]);

    app(SmokeSweep::class)->run();

    expect($panels->only)->toBe(['admin', '1', '2.5', 'team']);
});

it('sweeps nothing, not every panel, when panels.only only holds ids of panels that do not exist', function () {
    config()->set('filament-canary.panels.only', [404]);

    expect(app(SmokeSweep::class)->run())->toBe([]);
});

it('refuses a panel or page list it cannot read as strings, naming the config key', function (string $key, mixed $value, string $message) {
    config()->set($key, $value);

    expect(fn () => app(SmokeSweep::class)->run())->toThrow(InvalidArgumentException::class, $message);
})->with([
    'nested array' => ['filament-canary.panels.only', [['admin']], '[filament-canary.panels.only] must contain strings, got [array].'],
    'boolean' => ['filament-canary.panels.except', [true], '[filament-canary.panels.except] must contain strings, got [bool].'],
    'plain object' => ['filament-canary.exclude', [new stdClass], '[filament-canary.exclude] must contain strings, got [stdClass].'],
    'not a list' => ['filament-canary.panels.only', 'admin', '[filament-canary.panels.only] must be a list of strings, got [string].'],
]);
