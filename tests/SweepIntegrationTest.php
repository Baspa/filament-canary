<?php

use Baspa\FilamentCanary\Introspection\PanelCollector;
use Baspa\FilamentCanary\Sweep\KernelRequester;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Post;
use Baspa\FilamentCanary\Tests\Fixtures\Models\User;
use Illuminate\Routing\Redirector;
use Livewire\Features\SupportRedirects\Redirector as LivewireRedirector;

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

it('gets the same results when the sweep runs twice in one app', function () {
    $outcomes = fn () => array_map(
        fn (SweepResult $r) => [$r->target->routeName, $r->status, $r->authorizedStatus, $r->guestStatus, $r->reason],
        app(SmokeSweep::class)->run(),
    );

    $first = $outcomes();
    $second = $outcomes();

    expect($second)->toBe($first);
});

it('starts with a clean session after a request the test made itself', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertOk();

    $this->canarySweep();
});

it("leaves no session behind for the test's own requests", function () {
    $this->canarySweep();

    $this->actingAs(User::factory()->create())->get('/admin')->assertOk();
});

it('leaves the default guard as it found it', function () {
    config()->set('auth.guards.other', ['driver' => 'session', 'provider' => 'users']);
    auth()->shouldUse('other');

    app(SmokeSweep::class)->run();

    expect(auth()->getDefaultDriver())->toBe('other');
});

it("leaves Laravel's redirector bound after a page that aborts in mount()", function () {
    $result = sweepByRoute()['filament.admin.resources.secrets.index'];

    expect($result->authorizedStatus)->toBe(403)
        ->and(get_class(app('redirect')))->toBe(Redirector::class);
});

it("starts a guest request with Laravel's redirector when Livewire's is still bound", function () {
    // What Livewire 3 leaves bound after the test's own request to a page that aborts in mount().
    app()->bind('redirect', fn () => app(LivewireRedirector::class));

    $status = app(KernelRequester::class)->get(route('filament.admin.pages.dashboard'), null, 'web');

    expect($status)->toBe(302)
        ->and(get_class(app('redirect')))->toBe(Redirector::class);
});

it("keeps the test's own session data", function () {
    $this->withSession(['canary-test' => 'kept']);

    $this->canarySweep();

    expect(session('canary-test'))->toBe('kept');
});

it('keeps a custom user resolver', function () {
    $resolver = fn () => null;
    auth()->resolveUsersUsing($resolver);

    $this->canarySweep();

    expect(auth()->userResolver())->toBe($resolver);
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
