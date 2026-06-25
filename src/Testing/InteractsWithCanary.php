<?php

namespace Baspa\FilamentCanary\Testing;

use Baspa\FilamentCanary\Sweep\SmokeSweep;
use Baspa\FilamentCanary\Sweep\SweepResult;

/**
 * Drop this into a Pest/PHPUnit test to enforce the whole sweep in one assertion:
 *
 *     uses(Baspa\FilamentCanary\Testing\InteractsWithCanary::class);
 *     it('every Filament page mounts and authorizes', fn () => $this->canarySweep());
 */
trait InteractsWithCanary
{
    /**
     * Run the sweep and assert no page failed. Returns every result for further assertions.
     *
     * @return list<SweepResult>
     */
    public function canarySweep(): array
    {
        $results = app(SmokeSweep::class)->run();

        $strict = (bool) config('filament-canary.strict_authorization', false);

        $failures = array_values(array_filter(
            $results,
            fn (SweepResult $result) => $result->isFailure($strict),
        ));

        $this->assertSame([], $failures, $this->describeFailures($failures));

        return $results;
    }

    /**
     * @param  list<SweepResult>  $failures
     */
    protected function describeFailures(array $failures): string
    {
        if ($failures === []) {
            return '';
        }

        $lines = array_map(
            fn (SweepResult $result) => sprintf(
                '  %s %s — %s',
                $result->status->icon(),
                $result->target->label(),
                $result->reason ?? 'unknown',
            ),
            $failures,
        );

        return count($failures)." Filament page(s) failed the canary sweep:\n".implode("\n", $lines);
    }
}
