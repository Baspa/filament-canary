<?php

use Baspa\FilamentCanary\Install\AccessAnalyzer;
use Baspa\FilamentCanary\Install\AccessProposal;
use Baspa\FilamentCanary\Install\ConfigWriter;
use Baspa\FilamentCanary\Tests\Fixtures\Models\EmailUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\FlagUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\RoleUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\User;
use Filament\Panel;

function analyzeWith(string $model): AccessProposal
{
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', $model);

    return (new AccessAnalyzer)->analyze(Panel::make()->id('admin'));
}

it('treats a return-true gate as open (no resolver needed)', function () {
    $p = analyzeWith(User::class); // fixture User returns true

    expect($p->needsActingAs)->toBeFalse()
        ->and($p->confidence)->toBe('high');
});

it('proposes assignRole for a role-based gate', function () {
    $p = analyzeWith(RoleUser::class);

    expect($p->confidence)->toBe('high')
        ->and($p->actingAsExpression)->toContain("assignRole('admin')")
        ->and($p->actingAsExpression)->toContain(RoleUser::class);
});

it('proposes a factory flag for a boolean gate', function () {
    $p = analyzeWith(FlagUser::class);

    expect($p->actingAsExpression)->toContain("'is_admin' => true")
        ->and($p->confidence)->toBe('medium');
});

it('proposes a matching email for an allowlist gate', function () {
    $p = analyzeWith(EmailUser::class);

    expect($p->actingAsExpression)->toContain('@acme.test')
        ->and($p->confidence)->toBe('medium');
});

it('writes a config block from proposals', function () {
    $writer = new ConfigWriter;

    $proposals = [
        new AccessProposal('admin', 'web', RoleUser::class, true,
            "fn (\\Filament\\Panel \$panel) => \\App\\Models\\User::factory()->create()->assignRole('admin')",
            null, 'high', 'Role-based access detected.'),
    ];

    $block = $writer->actingAsBlock($proposals);

    expect($block)->toContain("'admin' =>")
        ->and($block)->toContain('assignRole')
        ->and($writer->generate($proposals))->toContain("'acting_as' =>");
});

it('returns null block when no panel needs a resolver', function () {
    $writer = new ConfigWriter;

    $proposals = [
        new AccessProposal('admin', 'web', User::class, false, null, null, 'high', 'open'),
    ];

    expect($writer->actingAsBlock($proposals))->toBe('null');
});
