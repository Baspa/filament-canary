<?php

namespace Baspa\FilamentCanary\Commands;

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepStatus;
use Baspa\FilamentCanary\Sweep\SweepVerdict;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'canary:check
        {--strict : Treat authorization gaps (needs-auth) as failures}
        {--fail-on-skipped : Treat skipped pages as failures}';

    protected $description = 'Smoke-sweep every Filament page: assert it mounts for an authorized user and denies guests.';

    public function handle(SmokeSweep $sweep): int
    {
        $results = $sweep->run();

        if ($results !== []) {
            $this->render($results);
        }

        $verdict = SweepVerdict::judge(
            $results,
            strictAuthorization: (bool) $this->option('strict'),
            failOnSkipped: (bool) $this->option('fail-on-skipped'),
        );

        if (($problem = $verdict->problem()) !== null) {
            $this->components->error($problem);
        }

        return $verdict->passed() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<SweepResult>  $results
     */
    protected function render(array $results): void
    {
        $rows = array_map(fn (SweepResult $r) => [
            $r->status->icon(),
            $r->target->panelId,
            $r->target->label(),
            $r->reason ?? $this->statuses($r),
        ], $results);

        $this->table(['', 'Panel', 'Page', 'Detail'], $rows);

        $this->summary($results);
    }

    protected function statuses(SweepResult $r): string
    {
        $parts = [];

        if ($r->authorizedStatus !== null) {
            $parts[] = "authorized: {$r->authorizedStatus}";
        }

        if ($r->guestStatus !== null) {
            $parts[] = "guest: {$r->guestStatus}";
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<SweepResult>  $results
     */
    protected function summary(array $results): void
    {
        $count = fn (SweepStatus $status) => count(array_filter($results, fn (SweepResult $r) => $r->status === $status));

        $this->newLine();
        $this->line(sprintf(
            '%s %d passed   %s %d failed   %s %d needs-auth   %s %d skipped',
            SweepStatus::Passed->icon(), $count(SweepStatus::Passed),
            SweepStatus::Failed->icon(), $count(SweepStatus::Failed),
            SweepStatus::NeedsAuth->icon(), $count(SweepStatus::NeedsAuth),
            SweepStatus::Skipped->icon(), $count(SweepStatus::Skipped),
        ));
    }
}
