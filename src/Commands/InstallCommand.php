<?php

namespace Baspa\FilamentCanary\Commands;

use Baspa\FilamentCanary\Install\AccessAnalyzer;
use Baspa\FilamentCanary\Install\AccessProposal;
use Baspa\FilamentCanary\Install\ConfigWriter;
use Baspa\FilamentCanary\Introspection\PanelCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'canary:install';

    protected $description = 'Inspect your panels and propose a filament-canary config (acting_as / tenant resolvers).';

    public function handle(PanelCollector $panels, AccessAnalyzer $analyzer, ConfigWriter $writer): int
    {
        $collected = $panels->collect();

        if ($collected === []) {
            $this->components->warn('No Filament panels found. Is a panel registered?');

            return self::SUCCESS;
        }

        /** @var list<AccessProposal> $proposals */
        $proposals = array_map(fn ($panel) => $analyzer->analyze($panel), $collected);

        $this->render($proposals);

        $path = config_path('filament-canary.php');

        if (File::exists($path) && ! $this->confirm('config/filament-canary.php already exists. Overwrite it?', false)) {
            return $this->printSnippet($writer, $proposals);
        }

        if (! $this->confirm('Write these proposals to config/filament-canary.php?', true)) {
            return $this->printSnippet($writer, $proposals);
        }

        File::put($path, $writer->generate($proposals));

        $this->components->info('Wrote config/filament-canary.php — review the proposed resolvers.');
        $this->line('Then add a test: <comment>$this->canarySweep();</comment> (via Baspa\\FilamentCanary\\Testing\\InteractsWithCanary)');

        return self::SUCCESS;
    }

    /**
     * @param  list<AccessProposal>  $proposals
     */
    protected function render(array $proposals): void
    {
        $rows = array_map(fn (AccessProposal $p) => [
            $p->panelId,
            $p->needsActingAs ? ($p->actingAsExpression !== null ? 'acting_as proposed' : 'manual') : 'open (no resolver)',
            $p->tenantExpression !== null ? 'yes' : '—',
            $p->confidence,
            $p->explanation,
        ], $proposals);

        $this->table(['Panel', 'Access', 'Tenant', 'Confidence', 'Notes'], $rows);
    }

    /**
     * @param  list<AccessProposal>  $proposals
     */
    protected function printSnippet(ConfigWriter $writer, array $proposals): int
    {
        $this->newLine();
        $this->line('Add the following to <comment>config/filament-canary.php</comment>:');
        $this->newLine();
        $this->line("    'acting_as' => ".$writer->actingAsBlock($proposals).',');
        $this->line("    'tenant' => ".$writer->tenantBlock($proposals).',');

        return self::SUCCESS;
    }
}
