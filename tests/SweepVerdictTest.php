<?php

use Baspa\FilamentCanary\Introspection\PageTarget;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Sweep\SweepVerdict;
use PHPUnit\Framework\AssertionFailedError;

function resultWithStatus(SweepStatus $status): SweepResult
{
    $target = new PageTarget('admin', 'web', "filament.admin.pages.{$status->value}", 'admin', [], false, false);

    return new SweepResult($target, $status, $status === SweepStatus::Passed ? null : "{$status->value} reason");
}

function breakActingUser(): void
{
    config()->set('filament-canary.acting_as', fn () => throw new RuntimeException('no such table: users'));
}

it('passes when a page passed and the rest were skipped or need auth', function () {
    $verdict = SweepVerdict::judge([
        resultWithStatus(SweepStatus::Passed),
        resultWithStatus(SweepStatus::Skipped),
        resultWithStatus(SweepStatus::NeedsAuth),
    ]);

    expect($verdict->passed())->toBeTrue()
        ->and($verdict->problem)->toBeNull();
});

it('fails when a page failed', function () {
    $failed = resultWithStatus(SweepStatus::Failed);

    $verdict = SweepVerdict::judge([resultWithStatus(SweepStatus::Passed), $failed]);

    expect($verdict->passed())->toBeFalse()
        ->and($verdict->failures)->toBe([$failed])
        ->and($verdict->problem)->toBeNull();
});

it('fails when no pages were found', function () {
    $verdict = SweepVerdict::judge([]);

    expect($verdict->passed())->toBeFalse()
        ->and($verdict->problem)->toContain('No Filament pages found');
});

it('fails when no page passed', function () {
    $verdict = SweepVerdict::judge([
        resultWithStatus(SweepStatus::Skipped),
        resultWithStatus(SweepStatus::NeedsAuth),
    ]);

    expect($verdict->passed())->toBeFalse()
        ->and($verdict->failures)->toBe([])
        ->and($verdict->problem)->toContain('No Filament page passed');
});

it('counts needs-auth pages as failures in strict mode', function () {
    $needsAuth = resultWithStatus(SweepStatus::NeedsAuth);
    $results = [resultWithStatus(SweepStatus::Passed), $needsAuth];

    expect(SweepVerdict::judge($results)->passed())->toBeTrue()
        ->and(SweepVerdict::judge($results, strictAuthorization: true)->failures)->toBe([$needsAuth]);

    config()->set('filament-canary.strict_authorization', true);

    expect(SweepVerdict::judge($results)->failures)->toBe([$needsAuth]);
});

it('counts skipped pages as failures with fail_on_skipped', function () {
    $skipped = resultWithStatus(SweepStatus::Skipped);
    $results = [resultWithStatus(SweepStatus::Passed), $skipped];

    expect(SweepVerdict::judge($results)->passed())->toBeTrue()
        ->and(SweepVerdict::judge($results, failOnSkipped: true)->failures)->toBe([$skipped]);

    config()->set('filament-canary.fail_on_skipped', true);

    expect(SweepVerdict::judge($results)->failures)->toBe([$skipped]);
});

it('fails canary:check when no pages were found', function () {
    config()->set('filament-canary.panels.only', ['does-not-exist']);

    $this->artisan('canary:check')
        ->expectsOutputToContain('No Filament pages found')
        ->assertExitCode(1);
});

it('fails canary:check when every page was skipped', function (string $command) {
    breakActingUser();

    $this->artisan($command)
        ->expectsOutputToContain('No Filament page passed')
        ->assertExitCode(1);
})->with(['canary:check', 'canary:check --strict']);

it('fails canary:check on a skipped page with --fail-on-skipped', function () {
    $this->artisan('canary:check --fail-on-skipped')->assertExitCode(1);
});

it('fails canary:check on a skipped page when fail_on_skipped is configured', function () {
    config()->set('filament-canary.fail_on_skipped', true);

    $this->artisan('canary:check')->assertExitCode(1);
});

it('fails canarySweep() when no pages were found', function () {
    config()->set('filament-canary.panels.only', ['does-not-exist']);

    expect(fn () => $this->canarySweep())->toThrow(AssertionFailedError::class, 'No Filament pages found');
});

it('fails canarySweep() when every page was skipped, listing why', function () {
    breakActingUser();

    expect(fn () => $this->canarySweep())
        ->toThrow(AssertionFailedError::class, 'No Filament page passed')
        ->toThrow(AssertionFailedError::class, 'no such table: users');
});

it('fails canarySweep() on a skipped page when fail_on_skipped is configured', function () {
    config()->set('filament-canary.fail_on_skipped', true);

    expect(fn () => $this->canarySweep())
        ->toThrow(AssertionFailedError::class, '1 Filament page(s) failed the canary sweep')
        ->toThrow(AssertionFailedError::class, 'filament.admin.resources.tags.edit');
});
