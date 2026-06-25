<?php

namespace Baspa\FilamentCanary\Sweep;

enum SweepStatus: string
{
    /** The page mounted for an authorized user and denied guests. */
    case Passed = 'passed';

    /** The page returned a server error, or leaked access to guests. A real failure. */
    case Failed = 'failed';

    /**
     * The page could not be reached by the resolved "authorized" user (401/403).
     * Almost always means the acting_as resolver needs configuring for this panel.
     * Actionable, not a hard failure (unless strict_authorization is enabled).
     */
    case NeedsAuth = 'needs_auth';

    /**
     * The page could not be swept automatically (no model factory, tenant resolver
     * required, unfillable route parameters). Visible on purpose — nothing is silently
     * left untested.
     */
    case Skipped = 'skipped';

    public function icon(): string
    {
        return match ($this) {
            self::Passed => '✅',
            self::Failed => '❌',
            self::NeedsAuth => '🔒',
            self::Skipped => '⏭️',
        };
    }
}
