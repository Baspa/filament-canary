<?php

namespace Baspa\FilamentCanary\Sweep;

/**
 * Whether a sweep as a whole passes. canary:check and canarySweep() both decide here,
 * so they can't disagree.
 */
class SweepVerdict
{
    /**
     * @param  list<SweepResult>  $results
     * @param  list<SweepResult>  $failures
     */
    public function __construct(
        public readonly array $results,
        public readonly array $failures,
    ) {}

    /**
     * @param  list<SweepResult>  $results
     */
    public static function judge(array $results, bool $strictAuthorization = false, bool $failOnSkipped = false): self
    {
        $strictAuthorization = $strictAuthorization || (bool) config('filament-canary.strict_authorization', false);
        $failOnSkipped = $failOnSkipped || (bool) config('filament-canary.fail_on_skipped', false);

        $failures = array_values(array_filter(
            $results,
            fn (SweepResult $result) => $result->isFailure($strictAuthorization, $failOnSkipped),
        ));

        return new self($results, $failures);
    }

    public function passed(): bool
    {
        return $this->failures === [] && $this->problem() === null;
    }

    /**
     * Why the sweep failed when no page counts as a failure on its own.
     */
    public function problem(): ?string
    {
        if ($this->results === []) {
            return 'No Filament pages found to sweep. Is a panel registered, and do the filament-canary panels and exclude settings leave any pages?';
        }

        if ($this->failures === [] && ! $this->anyPagePassed()) {
            return 'No Filament page passed, so the sweep proved nothing.';
        }

        return null;
    }

    public function anyPagePassed(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === SweepStatus::Passed) {
                return true;
            }
        }

        return false;
    }
}
