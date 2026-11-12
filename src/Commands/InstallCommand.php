<?php

namespace Baspa\FilamentCanary\Commands;

use Baspa\FilamentCanary\Install\AccessAnalyzer;
use Baspa\FilamentCanary\Install\AccessProposal;
use Baspa\FilamentCanary\Install\ConfigWriter;
use Baspa\FilamentCanary\Install\ResolverClass;
use Baspa\FilamentCanary\Introspection\PanelCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'canary:install';

    protected $description = 'Inspect your panels and propose a filament-canary config (acting_as / tenant resolvers).';

    public function handle(PanelCollector $panels, AccessAnalyzer $analyzer, ConfigWriter $writer): int
    {
        $collected = $panels->collect(
            $this->panelIds('filament-canary.panels.only'),
            $this->panelIds('filament-canary.panels.except'),
        );

        if ($collected === []) {
            $this->components->warn('No Filament panels found. Is a panel registered?');

            return self::SUCCESS;
        }

        /** @var list<AccessProposal> $proposals */
        $proposals = array_map(fn ($panel) => $analyzer->analyze($panel), $collected);

        $this->render($proposals);

        $namespace = ResolverClass::namespaceFor(base_path('composer.json'));
        [$actingAs, $tenants] = ResolverClass::forProposals($proposals, $namespace);
        $classes = [...$actingAs, ...$tenants];

        $path = config_path('filament-canary.php');
        $overwriting = File::exists($path);

        if ($overwriting && ! $this->confirm('config/filament-canary.php already exists. Overwrite it?', false)) {
            return $this->printPreview($writer, $actingAs, $tenants);
        }

        if ($classes !== []) {
            $this->line('The resolvers are written as invokable classes:');
            $this->components->bulletList(array_map(fn (ResolverClass $class) => $class->path(), $classes));
        }

        if (! $this->confirm('Write these proposals to config/filament-canary.php?', true)) {
            return $this->printPreview($writer, $actingAs, $tenants);
        }

        foreach ($classes as $class) {
            $this->writeClass($class);
        }

        File::put($path, $writer->generate($actingAs, $tenants, $overwriting ? $this->currentSettings($writer) : []));

        $this->components->info('Wrote config/filament-canary.php — review the proposed resolvers.');
        $this->line('Then add a test: <comment>$this->canarySweep();</comment> (via Baspa\\FilamentCanary\\Testing\\InteractsWithCanary)');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function panelIds(string $configKey): array
    {
        $ids = config($configKey, []);

        return is_array($ids) ? array_values(array_filter($ids, is_string(...))) : [];
    }

    /**
     * The current values of the non-resolver options, so overwriting the config keeps them.
     *
     * @return array<string, mixed>
     */
    protected function currentSettings(ConfigWriter $writer): array
    {
        $settings = [];

        foreach (array_keys($writer->defaults()) as $key) {
            if (in_array($key, ['acting_as', 'tenant'], true)) {
                continue;
            }

            $value = config("filament-canary.{$key}");

            if (ConfigWriter::exportable($value)) {
                $settings[$key] = $value;
            } else {
                $this->components->warn("Could not carry over [filament-canary.{$key}] because it holds a closure or object; wrote the default instead.");
            }
        }

        return $settings;
    }

    protected function writeClass(ResolverClass $class): void
    {
        $path = base_path($class->path());

        if (File::exists($path) && ! $this->confirm("{$class->path()} already exists. Overwrite it?", false)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $class->source);
    }

    /**
     * @param  list<AccessProposal>  $proposals
     */
    protected function render(array $proposals): void
    {
        $rows = array_map(fn (AccessProposal $p) => [
            $p->panelId,
            $p->needsActingAs ? ($p->actingAsExpression !== null ? 'acting_as proposed' : 'manual') : 'open (no resolver)',
            match (true) {
                $p->tenantExpression !== null => 'resolver proposed',
                $p->tenantExplanation !== null => 'default',
                default => '—',
            },
            $p->confidence,
            trim("{$p->explanation} {$p->tenantExplanation}"),
        ], $proposals);

        $this->table(['Panel', 'Access', 'Tenant', 'Confidence', 'Notes'], $rows);
    }

    /**
     * @param  list<ResolverClass>  $actingAs
     * @param  list<ResolverClass>  $tenants
     */
    protected function printPreview(ConfigWriter $writer, array $actingAs, array $tenants): int
    {
        $this->newLine();
        $this->line('Nothing was written. To set it up yourself:');

        foreach ([...$actingAs, ...$tenants] as $class) {
            $this->newLine();
            $this->line("<comment>{$class->path()}</comment>");
            $this->newLine();
            $this->line($class->source);
        }

        $this->newLine();
        $this->line('In <comment>config/filament-canary.php</comment>:');
        $this->newLine();
        $this->line("    'acting_as' => ".$writer->block($actingAs).',');
        $this->line("    'tenant' => ".$writer->block($tenants).',');

        return self::SUCCESS;
    }
}
