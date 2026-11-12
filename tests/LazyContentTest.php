<?php

use Baspa\FilamentCanary\Sweep\Requester;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\DeferredPostResource\Pages\ListDeferredPosts;
use Baspa\FilamentCanary\Tests\Fixtures\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Features\SupportLazyLoading\SupportLazyLoading;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('filament-canary.panels.only', ['lazy']);
});

afterEach(function () {
    SupportLazyLoading::$disableWhileTesting = false;
});

function lazyPanelResult(string $routeName): SweepResult
{
    $result = collect(app(SmokeSweep::class)->run())
        ->first(fn (SweepResult $result) => $result->target->routeName === $routeName);

    expect($result)->toBeInstanceOf(SweepResult::class, "no result for route {$routeName}");

    return $result;
}

it('fails a dashboard whose lazy widget throws', function () {
    $result = lazyPanelResult('filament.lazy.pages.dashboard');

    expect($result->status)->toBe(SweepStatus::Failed)
        ->and($result->authorizedStatus)->toBe(500);
});

it('fails a list page whose deferred table query throws', function () {
    $result = lazyPanelResult('filament.lazy.resources.deferred-posts.index');

    expect($result->status)->toBe(SweepStatus::Failed)
        ->and($result->authorizedStatus)->toBe(500);
});

it('switches lazy loading back on after the sweep', function () {
    app(SmokeSweep::class)->run();

    expect(SupportLazyLoading::$disableWhileTesting)->toBeFalse();
});

it('leaves lazy loading off after the sweep when it was already off', function () {
    Livewire::withoutLazyLoading();

    app(SmokeSweep::class)->run();

    expect(SupportLazyLoading::$disableWhileTesting)->toBeTrue();
});

it('restores lazy loading when the sweep throws', function () {
    app()->instance(Requester::class, new class implements Requester
    {
        public function get(string $url, ?Authenticatable $user, string $guard): int
        {
            throw new RuntimeException('requester broke');
        }
    });

    expect(fn () => app(SmokeSweep::class)->run())->toThrow(RuntimeException::class, 'requester broke')
        ->and(SupportLazyLoading::$disableWhileTesting)->toBeFalse();
});

it('stops loading deferred tables after the sweep', function () {
    app(SmokeSweep::class)->run();

    Filament::setCurrentPanel(Filament::getPanel('lazy'));
    $this->actingAs(User::factory()->create());

    Livewire::test(ListDeferredPosts::class)->assertSet('isTableLoaded', false);
});
