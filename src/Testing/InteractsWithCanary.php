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

        $this->assertTrue($verdict->passed(), $this->describeVerdict($verdict, $results));

        return $results;
    }

    /**
     * @param  list<SweepResult>  $results
     */
    protected function describeVerdict(SweepVerdict $verdict, array $results): string
    {
        if ($verdict->passed()) {
            return '';
        }

        $headline = $verdict->problem ?? count($verdict->failures).' Filament page(s) failed the canary sweep:';

        // With nothing passed and nothing failed, the skip and needs-auth reasons are the only clue.
        $listed = $verdict->failures === [] ? $results : $verdict->failures;

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
