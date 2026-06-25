<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Introspection\PageTarget;

class SweepResult
{
    public function __construct(
        public readonly PageTarget $target,
        public readonly SweepStatus $status,
        public readonly ?string $reason = null,
        public readonly ?int $authorizedStatus = null,
        public readonly ?int $guestStatus = null,
    ) {}

    public static function passed(PageTarget $target, int $authorizedStatus, ?int $guestStatus = null): self
    {
        return new self($target, SweepStatus::Passed, null, $authorizedStatus, $guestStatus);
    }

    public static function failed(PageTarget $target, string $reason, ?int $authorizedStatus = null, ?int $guestStatus = null): self
    {
        return new self($target, SweepStatus::Failed, $reason, $authorizedStatus, $guestStatus);
    }

    public static function needsAuth(PageTarget $target, string $reason, ?int $authorizedStatus = null): self
    {
        return new self($target, SweepStatus::NeedsAuth, $reason, $authorizedStatus);
    }

    public static function skipped(PageTarget $target, string $reason): self
    {
        return new self($target, SweepStatus::Skipped, $reason);
    }

    public function isFailure(bool $strictAuthorization = false): bool
    {
        if ($this->status === SweepStatus::Failed) {
            return true;
        }

        return $strictAuthorization && $this->status === SweepStatus::NeedsAuth;
    }
}
