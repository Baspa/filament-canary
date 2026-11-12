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

    public static function actingAs(AccessProposal $proposal, string $namespace): ?self
    {
        if (! $proposal->needsActingAs || $proposal->actingAsExpression === null) {
            return null;
        }

        $name = Str::studly($proposal->panelId).'ActingAs';

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

    public static function tenant(AccessProposal $proposal, string $namespace): ?self
    {
        if ($proposal->tenantExpression === null) {
            return null;
        }

        $name = Str::studly($proposal->panelId).'Tenant';

        return new self(
            $proposal->panelId,
            $namespace,
            $name,
            $proposal->tenantExplanation ?? 'Ensure the acting user can access this tenant.',
            self::render($namespace, $name, [
                'Filament\Panel',
                'Illuminate\Contracts\Auth\Authenticatable',
                'Illuminate\Database\Eloquent\Model',
            ], 'Panel $panel, Authenticatable $user', 'Model', $proposal->tenantExpression),
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
                    return rtrim((string) $namespace, '\\').'\\Canary';
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
    protected static function render(string $namespace, string $name, array $imports, string $parameters, string $returnType, string $expression): string
    {
        $uses = implode("\n", array_map(fn (string $import) => "use {$import};", $imports));

        return <<<PHP
            <?php

            namespace {$namespace};

            {$uses}

            class {$name}
            {
                public function __invoke({$parameters}): {$returnType}
                {
                    return {$expression};
                }
            }

            PHP;
    }
}
