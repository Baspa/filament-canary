<?php

use Baspa\FilamentCanary\Introspection\PageTarget;
use Baspa\FilamentCanary\Introspection\PageTargetCollector;
use Baspa\FilamentCanary\Introspection\PanelCollector;
use Baspa\FilamentCanary\Sweep\ActingUserResolver;
use Baspa\FilamentCanary\Sweep\RecordResolver;
use Baspa\FilamentCanary\Sweep\Requester;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Sweep\TenantResolver;
use Filament\Panel;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

class FakeRequester implements Requester
{
    public function __construct(
        public int $authorized = 200,
        public int $guest = 302,
    ) {}

    public function get(string $url, ?Authenticatable $user, string $guard): int
    {
        return $user === null ? $this->guest : $this->authorized;
    }
}

/**
 * @param  list<PageTarget>  $targets
 */
function sweepWith(array $targets, Requester $requester, ?callable $actingAs = null): array
{
    config()->set('filament-canary.acting_as', $actingAs ?? fn (Panel $panel) => new GenericUser(['id' => 1]));

    $panel = Panel::make()->id('admin');

    $panels = new class($panel) extends PanelCollector
    {
        public function __construct(private Panel $panel) {}

        public function collect(array $only = [], array $except = []): array
        {
            return [$this->panel];
        }
    };

    $collector = new class($targets) extends PageTargetCollector
    {
        public function __construct(private array $targets) {}

        public function forPanel(Panel $panel, array $excludeResources = []): array
        {
            return $this->targets;
        }
    };

    $sweep = new SmokeSweep($panels, $collector, new ActingUserResolver, new RecordResolver, new TenantResolver, $requester);

    return $sweep->run();
}

function target(array $overrides = []): PageTarget
{
    Route::get('/canary-fake', fn () => '')->name('filament.admin.pages.fake');

    return new PageTarget(
        panelId: $overrides['panelId'] ?? 'admin',
        guard: $overrides['guard'] ?? 'web',
        routeName: $overrides['routeName'] ?? 'filament.admin.pages.fake',
        uri: $overrides['uri'] ?? 'admin/fake',
        parameterNames: $overrides['parameterNames'] ?? [],
        requiresRecord: $overrides['requiresRecord'] ?? false,
        tenantScoped: $overrides['tenantScoped'] ?? false,
        modelClass: $overrides['modelClass'] ?? null,
    );
}

it('marks a 5xx authorized response as a failure', function () {
    $results = sweepWith([target()], new FakeRequester(authorized: 500));

    expect($results[0]->status)->toBe(SweepStatus::Failed);
});

it('marks a guest 2xx on a protected page as an authorization leak', function () {
    $results = sweepWith([target()], new FakeRequester(authorized: 200, guest: 200));

    expect($results[0]->status)->toBe(SweepStatus::Failed)
        ->and($results[0]->reason)->toContain('leak');
});

it('passes when authorized succeeds and guest is denied', function () {
    $results = sweepWith([target()], new FakeRequester(authorized: 200, guest: 302));

    expect($results[0]->status)->toBe(SweepStatus::Passed);
});

it('treats a denied authorized user as needs-auth', function () {
    $results = sweepWith([target()], new FakeRequester(authorized: 403));

    expect($results[0]->status)->toBe(SweepStatus::NeedsAuth);
});

it('treats an authorized redirect as needs-auth', function () {
    $results = sweepWith([target()], new FakeRequester(authorized: 302));

    expect($results[0]->status)->toBe(SweepStatus::NeedsAuth);
});

it('skips tenant-scoped pages when no tenant can be resolved', function () {
    $t = target(['tenantScoped' => true, 'parameterNames' => ['tenant']]);

    $results = sweepWith([$t], new FakeRequester);

    expect($results[0]->status)->toBe(SweepStatus::Skipped)
        ->and($results[0]->reason)->toContain('tenant');
});

it('skips pages with route parameters it cannot resolve', function () {
    $t = target(['parameterNames' => ['author'], 'uri' => 'admin/{author}']);

    $results = sweepWith([$t], new FakeRequester);

    expect($results[0]->status)->toBe(SweepStatus::Skipped)
        ->and($results[0]->reason)->toContain('cannot resolve');
});

it('runs without a transaction when use_transaction is disabled', function () {
    config()->set('filament-canary.use_transaction', false);

    $results = sweepWith([target()], new FakeRequester(authorized: 200, guest: 302));

    expect($results[0]->status)->toBe(SweepStatus::Passed);
});

it('refuses to run in the production environment', function () {
    $sweep = new class(new PanelCollector, new PageTargetCollector(app('router')), new ActingUserResolver, new RecordResolver, new TenantResolver, new FakeRequester) extends SmokeSweep
    {
        protected function isProduction(): bool
        {
            return true;
        }
    };

    expect(fn () => $sweep->run())->toThrow(RuntimeException::class, 'production');
});

it('surfaces the real error when the acting user cannot be created', function () {
    $results = sweepWith([target()], new FakeRequester, function () {
        throw new RuntimeException('Could not verify the hashed value\'s configuration.');
    });

    expect($results[0]->status)->toBe(SweepStatus::Skipped)
        ->and($results[0]->reason)->toContain('could not create an authorized user')
        ->and($results[0]->reason)->toContain('hashed value');
});
