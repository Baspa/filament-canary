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
        /** Whether an acting_as resolver is needed at all (false when the panel is open). */
        public readonly bool $needsActingAs,
        /** PHP expression the acting_as resolver returns ($panel in scope), or null when none is needed. */
        public readonly ?string $actingAsExpression,
        /** PHP expression the tenant resolver returns ($panel and $user in scope), or null when the default tenant will do. */
        public readonly ?string $tenantExpression,
        /** high | medium | low | none */
        public readonly string $confidence,
        public readonly string $explanation,
        /** How the panel's tenant is handled, or null without tenancy. */
        public readonly ?string $tenantExplanation = null,
    ) {}
}
