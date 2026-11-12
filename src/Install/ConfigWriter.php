<?php

namespace Baspa\FilamentCanary\Install;

class ConfigWriter
{
    /**
     * Build the full config/filament-canary.php contents: the package's published config,
     * with acting_as / tenant pointing at the generated resolver classes.
     *
     * @param  list<ResolverClass>  $actingAs
     * @param  list<ResolverClass>  $tenants
     */
    public function generate(array $actingAs, array $tenants): string
    {
        $published = (string) file_get_contents(__DIR__.'/../../config/filament-canary.php');

        return str_replace(
            ["'acting_as' => null,", "'tenant' => null,"],
            ["'acting_as' => {$this->block($actingAs)},", "'tenant' => {$this->block($tenants)},"],
            $published,
        );
    }

    /**
     * @param  list<ResolverClass>  $classes
     */
    public function block(array $classes): string
    {
        if ($classes === []) {
            return 'null';
        }

        $entries = array_map(
            fn (ResolverClass $class) => "        // {$class->panelId} — {$class->note}\n"
                ."        '{$class->panelId}' => \\{$class->className()}::class,",
            $classes,
        );

        return "[\n".implode("\n", $entries)."\n    ]";
    }
}
