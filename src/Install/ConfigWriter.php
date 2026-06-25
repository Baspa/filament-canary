<?php

namespace Baspa\FilamentCanary\Install;

class ConfigWriter
{
    /**
     * Build the full config/filament-canary.php contents with the proposed resolvers
     * baked in as real closures.
     *
     * @param  list<AccessProposal>  $proposals
     */
    public function generate(array $proposals): string
    {
        return <<<PHP
            <?php

            // config for Baspa/FilamentCanary
            // acting_as / tenant below were proposed by `php artisan canary:install` — review them.
            return [

                'panels' => [
                    'only' => [],
                    'except' => [],
                ],

                'exclude' => [],

                'test_guests' => true,

                'strict_authorization' => false,

                'acting_as' => {$this->actingAsBlock($proposals)},

                'tenant' => {$this->tenantBlock($proposals)},

            ];

            PHP;
    }

    /**
     * @param  list<AccessProposal>  $proposals
     */
    public function actingAsBlock(array $proposals): string
    {
        $entries = [];

        foreach ($proposals as $p) {
            if (! $p->needsActingAs || $p->actingAsExpression === null) {
                continue;
            }

            $entries[] = "        // {$p->panelId} — {$p->explanation} (confidence: {$p->confidence})\n"
                ."        '{$p->panelId}' => {$p->actingAsExpression},";
        }

        if ($entries === []) {
            return 'null';
        }

        return "[\n".implode("\n", $entries)."\n    ]";
    }

    /**
     * @param  list<AccessProposal>  $proposals
     */
    public function tenantBlock(array $proposals): string
    {
        $entries = [];

        foreach ($proposals as $p) {
            if ($p->tenantExpression === null) {
                continue;
            }

            $entries[] = "        // {$p->panelId} — ensure the acting_as user can access this tenant\n"
                ."        '{$p->panelId}' => {$p->tenantExpression},";
        }

        if ($entries === []) {
            return 'null';
        }

        return "[\n".implode("\n", $entries)."\n    ]";
    }
}
