<?php

use Baspa\FilamentCanary\Install\AccessAnalyzer;
use Baspa\FilamentCanary\Install\AccessProposal;
use Baspa\FilamentCanary\Install\ConfigWriter;
use Baspa\FilamentCanary\Install\ResolverClass;
use Baspa\FilamentCanary\Tests\Fixtures\Models\EmailUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\FlagUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\HasManyUsersTeam;
use Baspa\FilamentCanary\Tests\Fixtures\Models\PermissionRoleUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\PermissionTenantUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\RoleUser;
use Baspa\FilamentCanary\Tests\Fixtures\Models\Team;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantTeam;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantUser;
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
    $p = analyzeWith(PermissionRoleUser::class);

    expect($p->confidence)->toBe('high')
        ->and($p->actingAsExpression)->toContain("assignRole('admin')")
        ->and($p->actingAsExpression)->toContain(PermissionRoleUser::class);
});

it('proposes a plain factory user when the model has hasRole() but no assignRole()', function () {
    $p = analyzeWith(RoleUser::class);

    expect($p->needsActingAs)->toBeTrue()
        ->and($p->confidence)->toBe('low')
        ->and($p->actingAsExpression)->toBe('\\'.RoleUser::class.'::factory()->create()')
        ->and($p->explanation)->toContain("role 'admin'")
        ->and($p->explanation)->toContain('no assignRole()');
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

it('detects a role gate in tenancy methods when canAccessPanel is open', function () {
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', PermissionTenantUser::class);

    $p = (new AccessAnalyzer)->analyze(Panel::make()->id('shop')->tenant(Team::class));

    expect($p->needsActingAs)->toBeTrue()
        ->and($p->actingAsExpression)->toContain("assignRole('owner')")
        ->and($p->tenantExpression)->toContain('Team::factory');
});

it('proposes a plain factory user for a tenancy role gate without assignRole()', function () {
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', TenantUser::class);

    $p = (new AccessAnalyzer)->analyze(Panel::make()->id('shop')->tenant(Team::class));

    expect($p->needsActingAs)->toBeTrue()
        ->and($p->confidence)->toBe('low')
        ->and($p->actingAsExpression)->not->toContain('assignRole')
        ->and($p->explanation)->toContain("role 'owner'");
});

it('proposes a tenant that the acting user is attached to when the tenant has a members relation', function () {
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', TeamMember::class);

    $p = (new AccessAnalyzer)->analyze(Panel::make()->id('shop')->tenant(TenantTeam::class));

    expect($p->tenantExpression)->toBe('\\'.TenantTeam::class."::factory()->hasAttached(\$user, [], 'members')->create()");
});

it('proposes a plain factory tenant with a hint when no members relation is found', function () {
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', TenantUser::class);

    $p = (new AccessAnalyzer)->analyze(Panel::make()->id('shop')->tenant(Team::class));

    expect($p->tenantExpression)->toBe('\\'.Team::class.'::factory()->create()')
        ->and($p->tenantExplanation)->toContain('hasAttached($user)');
});

it('writes config entries that point at the generated resolver classes', function () {
    $writer = new ConfigWriter;

    $class = ResolverClass::actingAs(new AccessProposal('admin', 'web', RoleUser::class, true,
        "\\App\\Models\\User::factory()->create()->assignRole('admin')",
        null, 'high', 'Role-based access detected.'), 'Tests\\Canary', 'Admin');

    $block = $writer->block([$class]);

    expect($block)->toContain("'admin' => \\Tests\\Canary\\AdminActingAs::class,")
        ->and($block)->toContain('Role-based access detected. (confidence: high)')
        ->and($writer->generate([$class], []))->toContain("'acting_as' => [");
});

it('writes no resolver class and a null block when no panel needs one', function () {
    $proposal = new AccessProposal('admin', 'web', User::class, false, null, null, 'high', 'open');

    expect(ResolverClass::forProposals([$proposal], 'Tests\\Canary'))->toBe([[], []])
        ->and((new ConfigWriter)->block([]))->toBe('null');
});

it('keeps every option and its docs from the published config', function () {
    $config = (new ConfigWriter)->generate([], []);

    expect($config)->toBe(file_get_contents(__DIR__.'/../config/filament-canary.php'))
        ->and($config)->toContain("'use_transaction' => true,");
});

it('generates invokable resolver classes that return the proposed expressions', function () {
    $proposal = new AccessProposal('shop.admin', 'web', RoleUser::class, true,
        '\\App\\Models\\User::factory()->create()', '\\App\\Models\\Team::factory()->hasAttached($user)->create()',
        'low', 'Plain factory user.', 'Attaches the user.');

    [[$actingAs], [$tenant]] = ResolverClass::forProposals([$proposal], 'Tests\\Canary');

    expect($actingAs->className())->toBe('Tests\\Canary\\ShopAdminActingAs')
        ->and($actingAs->path())->toBe('tests/Canary/ShopAdminActingAs.php')
        ->and($actingAs->source)->toContain('public function __invoke(Panel $panel): Authenticatable')
        ->and($actingAs->source)->toContain('return \\App\\Models\\User::factory()->create();')
        ->and($tenant->className())->toBe('Tests\\Canary\\ShopAdminTenant')
        ->and($tenant->source)->toContain('public function __invoke(Panel $panel, Authenticatable $user): Model')
        ->and($tenant->note)->toBe('Attaches the user.');

    // TOKEN_PARSE makes token_get_all() throw a ParseError on invalid PHP.
    token_get_all($actingAs->source, TOKEN_PARSE);
    token_get_all($tenant->source, TOKEN_PARSE);
});

it('puts generated classes in the namespace composer.json maps to tests/', function () {
    $composer = tempnam(sys_get_temp_dir(), 'canary-composer');

    try {
        file_put_contents($composer, json_encode(['autoload-dev' => ['psr-4' => [
            'Database\\Factories\\' => 'database/factories/',
            'App\\Tests\\' => 'tests/',
        ]]]));

        expect(ResolverClass::namespaceFor($composer))->toBe('App\\Tests\\Canary');
    } finally {
        unlink($composer);
    }

    expect(ResolverClass::namespaceFor('/does/not/exist/composer.json'))->toBe('Tests\\Canary');
});

it('uses Canary as the namespace when tests/ is mapped to the root namespace', function () {
    $composer = tempnam(sys_get_temp_dir(), 'canary-composer');

    try {
        file_put_contents($composer, json_encode(['autoload-dev' => ['psr-4' => ['' => 'tests/']]]));

        $namespace = ResolverClass::namespaceFor($composer);
    } finally {
        unlink($composer);
    }

    $proposal = new AccessProposal('admin', 'web', RoleUser::class, true, '\\App\\Models\\User::factory()->create()', null, 'low', 'Plain factory user.');
    [[$class]] = ResolverClass::forProposals([$proposal], $namespace);

    expect($namespace)->toBe('Canary')
        ->and($class->className())->toBe('Canary\\AdminActingAs')
        ->and($class->source)->toContain('namespace Canary;');

    token_get_all($class->source, TOKEN_PARSE);
});

it('turns panel ids into valid class name prefixes that are unique within a run', function () {
    expect(ResolverClass::prefixes(['app-admin', 'app_admin', 'app.admin', 'ADMIN', 'admin', '2fa', '...']))->toBe([
        'app-admin' => 'AppAdmin',
        'app_admin' => 'AppAdmin2',
        'app.admin' => 'AppAdmin3',
        'ADMIN' => 'ADMIN',
        'admin' => 'Admin2',
        '2fa' => 'Panel2fa',
        '...' => 'Panel',
    ]);
});

it('does not propose hasAttached() when the tenant relation is not many-to-many', function () {
    config()->set('auth.guards.web.provider', 'users');
    config()->set('auth.providers.users.model', TeamMember::class);

    $p = (new AccessAnalyzer)->analyze(Panel::make()->id('shop')->tenant(HasManyUsersTeam::class));

    expect($p->tenantExpression)->toBe('\\'.HasManyUsersTeam::class.'::factory()->create()');
});

it('carries over changed settings and leaves the rest of the published config as it is', function () {
    $writer = new ConfigWriter;
    $published = file_get_contents(__DIR__.'/../config/filament-canary.php');

    $config = $writer->generate([], [], [
        ...$writer->defaults(),
        'panels' => ['only' => ['admin'], 'except' => []],
        'test_guests' => false,
        'exclude' => ['App\\Filament\\Resources\\PostResource'],
    ]);

    $written = eval('?>'.$config);

    expect($written)->toBe([...$writer->defaults(), ...[
        'panels' => ['only' => ['admin'], 'except' => []],
        'exclude' => ['App\\Filament\\Resources\\PostResource'],
        'test_guests' => false,
    ]])
        ->and($config)->toContain("    'use_transaction' => true,\n")
        ->and(substr_count($config, '|---'))->toBe(substr_count($published, '|---'));
});

it('only accepts plain values as settings to carry over', function (mixed $value, bool $exportable) {
    expect(ConfigWriter::exportable($value))->toBe($exportable);
})->with([
    'scalar' => ['admin', true],
    'null' => [null, true],
    'nested array of scalars' => [['only' => ['admin'], 'except' => []], true],
    'closure' => [fn () => true, false],
    'object in an array' => [[new stdClass], false],
]);

it('fails loudly when the published config has no slot for a resolver', function () {
    $template = tempnam(sys_get_temp_dir(), 'canary-config');

    try {
        file_put_contents($template, "<?php\n\nreturn [\n\n    'acting_as' => null,\n\n];\n");

        expect(fn () => (new ConfigWriter($template))->generate([], []))
            ->toThrow(LogicException::class, 'Could not find the [tenant] option');
    } finally {
        unlink($template);
    }
});
