<?php

namespace Baspa\FilamentCanary\Testing;

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;
use Baspa\FilamentCanary\Sweep\SweepVerdict;

/**
 * Drop this into a Pest/PHPUnit test to enforce the whole sweep in one assertion:
 *
 *     uses(Baspa\FilamentCanary\Testing\InteractsWithCanary::class);
 *     it('every Filament page mounts and authorizes', fn () => $this->canarySweep());
 */
trait InteractsWithCanary
{
    /**
     * Run the sweep and assert it passed. Returns every result for further assertions.
     *
     * @return list<SweepResult>
     */
    public function canarySweep(): array
    {
        $results = app(SmokeSweep::class)->run();

        $verdict = SweepVerdict::judge($results);

        $this->assertTrue($verdict->passed(), $this->describeVerdict($verdict));

        return $results;
    }

    protected function describeVerdict(SweepVerdict $verdict): string
    {
        if ($verdict->passed()) {
            return '';
        }

        $failed = count($verdict->failures).' Filament page(s) failed the canary sweep';

        $headline = match (true) {
            $verdict->problem() !== null => $verdict->problem(),
            $verdict->anyPagePassed() => $failed.':',
            default => $failed.' and none passed:',
        };

        // When nothing passed, the skip and needs-auth reasons explain why.
        $listed = $verdict->anyPagePassed() ? $verdict->failures : $verdict->results;

        $lines = array_map(
            fn (SweepResult $result) => sprintf(
                '  %s %s — %s',
                $result->status->icon(),
                $result->target->label(),
                $result->reason ?? 'unknown',
            ),
            $listed,
        );

        return implode("\n", [$headline, ...$lines]);
    }
}
