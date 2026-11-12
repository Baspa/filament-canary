<?php

use Baspa\FilamentCanary\Introspection\PageTarget;
use Baspa\FilamentCanary\Introspection\PageTargetCollector;
use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource\Pages\CreatePost;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource\Pages\EditPost;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource\Pages\ListPosts;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource\Pages\ViewPost;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\SecretResource;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\SecretResource\Pages\ListSecrets;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TagResource;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TagResource\Pages\EditTag;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TagResource\Pages\ListTags;
use Filament\Facades\Filament;
use Filament\Http\Controllers\RedirectToHomeController;
use Filament\Http\Controllers\RedirectToTenantController;
use Filament\Pages\Dashboard;

/**
 * @param  list<string>  $exclude
 * @return list<string>
 */
function routeNamesCollectedFor(string $panelId, array $exclude = []): array
{
    return array_map(
        fn (PageTarget $target) => $target->routeName,
        app(PageTargetCollector::class)->forPanel(Filament::getPanel($panelId), $exclude),
    );
}

function registeredControllerClass(string $routeName): ?string
{
    $route = app('router')->getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull("route [{$routeName}] is not registered");

    return $route->getControllerClass();
}

it('collects the same admin pages as before, with their resource and page classes', function () {
    $targets = collect(app(PageTargetCollector::class)->forPanel(Filament::getPanel('admin')))
        ->mapWithKeys(fn (PageTarget $target) => [$target->routeName => [$target->resourceClass, $target->pageClass]])
        ->all();

    expect($targets)->toEqual([
        'filament.admin.pages.dashboard' => [null, Dashboard::class],
        'filament.admin.resources.posts.index' => [PostResource::class, ListPosts::class],
        'filament.admin.resources.posts.create' => [PostResource::class, CreatePost::class],
        'filament.admin.resources.posts.view' => [PostResource::class, ViewPost::class],
        'filament.admin.resources.posts.edit' => [PostResource::class, EditPost::class],
        'filament.admin.resources.tags.index' => [TagResource::class, ListTags::class],
        'filament.admin.resources.tags.edit' => [TagResource::class, EditTag::class],
        'filament.admin.resources.secrets.index' => [SecretResource::class, ListSecrets::class],
    ]);
});

it('collects the profile page but none of the auth flows', function () {
    foreach ([
        'filament.profile-page.auth.login',
        'filament.profile-page.auth.register',
        'filament.profile-page.auth.password-reset.request',
        'filament.profile-page.auth.password-reset.reset',
        'filament.profile-page.auth.email-change-verification.verify',
        'filament.profile-page.auth.email-change-verification.block-verification',
    ] as $authFlowRoute) {
        registeredControllerClass($authFlowRoute);
    }

    expect(routeNamesCollectedFor('profile-page'))->toEqualCanonicalizing([
        'filament.profile-page.pages.dashboard',
        'filament.profile-page.auth.profile',
    ]);
});

it('passes the profile page in a real sweep', function () {
    config()->set('filament-canary.panels.only', ['profile-page']);

    $results = collect(app(SmokeSweep::class)->run())
        ->keyBy(fn (SweepResult $result) => $result->target->routeName);

    expect($results->keys()->all())->toEqualCanonicalizing([
        'filament.profile-page.pages.dashboard',
        'filament.profile-page.auth.profile',
    ]);

    foreach ($results as $routeName => $result) {
        expect($result->status)->toBe(SweepStatus::Passed, "expected {$routeName} to pass, got: ".($result->reason ?? ''))
            ->and($result->authorizedStatus)->toBe(200)
            ->and($result->guestStatus)->toBeGreaterThanOrEqual(300);
    }
});

it('recognises auth flows on a multi-domain panel, whose route names include the domain', function () {
    foreach (['one.canary.test', 'two.canary.test'] as $domain) {
        foreach ([
            'auth.login',
            'auth.email-verification.prompt',
            'auth.email-verification.verify',
            'auth.multi-factor-authentication.set-up-required',
        ] as $authFlowRoute) {
            registeredControllerClass("filament.multi-domain-auth.{$domain}.{$authFlowRoute}");
        }
    }

    expect(routeNamesCollectedFor('multi-domain-auth'))->toEqualCanonicalizing([
        'filament.multi-domain-auth.one.canary.test.pages.dashboard',
        'filament.multi-domain-auth.one.canary.test.auth.profile',
        'filament.multi-domain-auth.two.canary.test.pages.dashboard',
        'filament.multi-domain-auth.two.canary.test.auth.profile',
    ]);
});

it('skips the redirect to the tenant', function () {
    expect(registeredControllerClass('filament.tenant-redirect.tenant'))->toBe(RedirectToTenantController::class)
        ->and(routeNamesCollectedFor('tenant-redirect'))->toBe(['filament.tenant-redirect.pages.dashboard']);
});

it('skips the redirect to the home page of a panel without a page at its root', function () {
    expect(registeredControllerClass('filament.no-root-page.home'))->toBe(RedirectToHomeController::class)
        ->and(routeNamesCollectedFor('no-root-page'))->toEqualCanonicalizing([
            'filament.no-root-page.resources.posts.index',
            'filament.no-root-page.resources.posts.create',
            'filament.no-root-page.resources.posts.view',
            'filament.no-root-page.resources.posts.edit',
        ]);
});

it('excludes a page by its class', function () {
    $all = routeNamesCollectedFor('admin');

    expect(routeNamesCollectedFor('admin', [Dashboard::class]))
        ->toEqualCanonicalizing(array_values(array_diff($all, ['filament.admin.pages.dashboard'])));
});

it('excludes only the resource page whose class is excluded', function () {
    $all = routeNamesCollectedFor('admin');

    expect(routeNamesCollectedFor('admin', [EditPost::class]))
        ->toEqualCanonicalizing(array_values(array_diff($all, ['filament.admin.resources.posts.edit'])));
});

it('excludes every page of an excluded resource', function () {
    $collected = routeNamesCollectedFor('admin', [PostResource::class]);

    expect($collected)->toEqualCanonicalizing([
        'filament.admin.pages.dashboard',
        'filament.admin.resources.tags.index',
        'filament.admin.resources.tags.edit',
        'filament.admin.resources.secrets.index',
    ]);
});

it('applies the exclude config to the sweep', function () {
    config()->set('filament-canary.exclude', [Dashboard::class]);

    $swept = array_map(fn (SweepResult $result) => $result->target->routeName, app(SmokeSweep::class)->run());

    expect($swept)->not->toBeEmpty()
        ->not->toContain('filament.admin.pages.dashboard');
});
