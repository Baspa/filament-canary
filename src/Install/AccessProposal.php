<?php

namespace Baspa\FilamentCanary\Install;

/**
 * A best-effort proposal for how to authenticate against one panel, derived from
 * reading the user model's access gate. Always a suggestion — the user confirms or edits.
 */
class AccessProposal
{
    public function __construct(
        public readonly string $panelId,
        public readonly string $guard,
        public readonly ?string $userModel,
        /** Whether an acting_as resolver is needed at all (false when the panel is open). */
        public readonly bool $needsActingAs,
        /** PHP source for the acting_as closure, or null when none is needed. */
        public readonly ?string $actingAsExpression,
        /** PHP source for the tenant closure, or null when the panel has no tenancy. */
        public readonly ?string $tenantExpression,
        /** high | medium | low | none */
        public readonly string $confidence,
        public readonly string $explanation,
    ) {}
}
