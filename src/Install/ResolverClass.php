<?php

namespace Baspa\FilamentCanary\Install;

use Illuminate\Support\Str;

/**
 * An invokable acting_as or tenant resolver that canary:install writes into the app's
 * tests directory. The config then only holds its class name, so `config:cache` works
 * and the class doesn't have to exist in production.
 */
class ResolverClass
{
    public const DIRECTORY = 'tests/Canary';

    public function __construct(
        public readonly string $panelId,
        public readonly string $namespace,
        public readonly string $name,
        /** Explanation written next to the class name in the config. */
        public readonly string $note,
        public readonly string $source,
    ) {}

    /**
     * The acting_as and tenant classes for these proposals, with class names that are
     * valid PHP and unique within the run.
     *
     * @param  list<AccessProposal>  $proposals
     * @return array{0: list<self>, 1: list<self>} [acting_as classes, tenant classes]
     */
    public static function forProposals(array $proposals, string $namespace): array
    {
        $prefixes = self::prefixes(array_map(fn (AccessProposal $p) => $p->panelId, $proposals));
        $actingAs = [];
        $tenants = [];

        foreach ($proposals as $proposal) {
            $prefix = $prefixes[$proposal->panelId];

            if ($class = self::actingAs($proposal, $namespace, $prefix)) {
                $actingAs[] = $class;
            }

            if ($class = self::tenant($proposal, $namespace, $prefix)) {
                $tenants[] = $class;
            }
        }

        return [$actingAs, $tenants];
    }

    /**
     * Class name prefix per panel id: letters and digits only, never starting with a digit,
     * and unique (case-insensitively, like PHP class names) by appending a number.
     *
     * @param  list<string>  $panelIds
     * @return array<string, string>
     */
    public static function prefixes(array $panelIds): array
    {
        $prefixes = [];
        $taken = [];

        foreach ($panelIds as $panelId) {
            $base = Str::studly((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $panelId));

            if ($base === '' || ctype_digit($base[0])) {
                $base = 'Panel'.$base;
            }

            $prefix = $base;

            for ($n = 2; isset($taken[strtolower($prefix)]); $n++) {
                $prefix = $base.$n;
            }

            $taken[strtolower($prefix)] = true;
            $prefixes[$panelId] = $prefix;
        }

        return $prefixes;
    }

    public static function actingAs(AccessProposal $proposal, string $namespace, string $prefix): ?self
    {
        if (! $proposal->needsActingAs || $proposal->actingAsExpression === null) {
            return null;
        }

        $name = "{$prefix}ActingAs";

        return new self(
            $proposal->panelId,
            $namespace,
            $name,
            "{$proposal->explanation} (confidence: {$proposal->confidence})",
            self::render($namespace, $name, [
                'Filament\Panel',
                'Illuminate\Contracts\Auth\Authenticatable',
            ], 'Panel $panel', 'Authenticatable', $proposal->actingAsExpression),
        );
    }

    public static function tenant(AccessProposal $proposal, string $namespace, string $prefix): ?self
    {
        if ($proposal->tenantExpression === null) {
            return null;
        }

        $name = "{$prefix}Tenant";

        return new self(
            $proposal->panelId,
            $namespace,
            $name,
            $proposal->tenantExplanation ?? 'Ensure the acting user can access this tenant.',
            self::render($namespace, $name, [
                'Filament\Panel',
                'Illuminate\Contracts\Auth\Authenticatable',
                'Illuminate\Database\Eloquent\Model',
            ], 'Panel $panel, Authenticatable $user', 'Model', $proposal->tenantExpression,
                "Make \$user a member of the tenant so canAccessTenant() lets it in, e.g. ->hasAttached(\$user, [], 'members').",
            ),
        );
    }

    /**
     * The namespace for generated classes: the one composer.json's autoload-dev maps to
     * tests/, plus "Canary". Falls back to Laravel's default Tests\ mapping.
     */
    public static function namespaceFor(string $composerJsonPath): string
    {
        $composer = is_file($composerJsonPath)
            ? json_decode((string) file_get_contents($composerJsonPath), true)
            : null;

        $psr4 = data_get($composer, 'autoload-dev.psr-4');

        foreach (is_array($psr4) ? $psr4 : [] as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if (is_string($path) && in_array(rtrim($path, '/'), ['tests', './tests'], true)) {
                    return ltrim(rtrim((string) $namespace, '\\').'\\Canary', '\\');
                }
            }
        }

        return 'Tests\\Canary';
    }

    public function className(): string
    {
        return "{$this->namespace}\\{$this->name}";
    }

    /**
     * Relative to the app's base path.
     */
    public function path(): string
    {
        return self::DIRECTORY."/{$this->name}.php";
    }

    /**
     * @param  list<string>  $imports
     */
    protected static function render(string $namespace, string $name, array $imports, string $parameters, string $returnType, string $expression, ?string $hint = null): string
    {
        $uses = implode("\n", array_map(fn (string $import) => "use {$import};", $imports));
        $hint = $hint === null ? '' : "// {$hint}\n        ";

        return <<<PHP
            <?php

            namespace {$namespace};

            {$uses}

            class {$name}
            {
                public function __invoke({$parameters}): {$returnType}
                {
                    {$hint}return {$expression};
                }
            }

            PHP;
    }
}
