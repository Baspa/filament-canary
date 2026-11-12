<?php

namespace Baspa\FilamentCanary\Install;

use Baspa\FilamentCanary\Support\TenantMembership;
use Closure;
use Filament\Panel;
use ReflectionClass;

/**
 * Reads a panel's access gate (the user model's canAccessPanel, plus tenancy methods)
 * and proposes an acting_as / tenant resolver. Heuristic by nature: when it can't read
 * the gate with confidence it falls back to a plain factory user and says so.
 */
class AccessAnalyzer
{
    public function analyze(Panel $panel): AccessProposal
    {
        $userModel = $this->guardModel($panel->getAuthGuard());
        [$tenantExpression, $tenantExplanation] = $this->proposeTenant($panel, $userModel);

        $propose = fn (bool $needsActingAs, ?string $actingAsExpression, string $confidence, string $explanation) => new AccessProposal(
            $panel->getId(), $panel->getAuthGuard(), $userModel, $needsActingAs, $actingAsExpression,
            $tenantExpression, $confidence, $explanation, $tenantExplanation,
        );

        if ($userModel === null) {
            return $propose(true, null, 'none', "Could not resolve a user model for guard [{$panel->getAuthGuard()}]; set acting_as manually.");
        }

        $source = $this->methodSource($userModel, 'canAccessPanel');
        $user = '\\'.ltrim($userModel, '\\');

        // No panel gate / explicitly open. The default factory user passes canAccessPanel —
        // but for a tenant panel the real gate often lives in getTenants/canAccessTenant,
        // so look there before declaring the panel open.
        if ($source === null || $this->isOpen($source)) {
            if ($panel->hasTenancy() && ($tenantRole = $this->detectTenancyRole($userModel))) {
                return $this->proposeRole(
                    $propose, $userModel, $tenantRole, 'medium',
                    "canAccessPanel() is open, but tenant access requires role '{$tenantRole}' (from getTenants/canAccessTenant).",
                );
            }

            return $propose(false, null, 'high', $source === null
                ? 'No canAccessPanel() override found; a plain factory user should work.'
                : 'canAccessPanel() returns true; a plain factory user should work.');
        }

        if ($role = $this->detectRole($source)) {
            return $this->proposeRole($propose, $userModel, $role, 'high', "Role-based access detected (role '{$role}').");
        }

        if ($column = $this->detectBooleanFlag($source)) {
            return $propose(
                true, "{$user}::factory()->create(['{$column}' => true])", 'medium',
                "Boolean flag access detected ([{$column}]). Adjust if your factory needs a different value.",
            );
        }

        if ($this->detectsEmailGate($source)) {
            $domain = $this->detectEmailDomain($source);
            $email = $domain ? "admin{$domain}" : 'admin@example.test';

            return $propose(
                true, "{$user}::factory()->create(['email' => '{$email}'])", 'medium',
                'Email-based access detected. Replace the email with one your allowlist accepts.',
            );
        }

        return $propose(
            true, "{$user}::factory()->create()", 'low',
            "Could not read the access gate confidently; using a plain factory user. Adjust if this panel's pages come back as needs-auth.",
        );
    }

    /**
     * @param  Closure(bool, ?string, string, string): AccessProposal  $propose
     */
    protected function proposeRole(Closure $propose, string $userModel, string $role, string $confidence, string $detected): AccessProposal
    {
        $user = '\\'.ltrim($userModel, '\\');

        // An app's own hasRole() often comes without spatie/laravel-permission's assignRole().
        if (! method_exists($userModel, 'assignRole')) {
            return $propose(
                true, "{$user}::factory()->create()", 'low',
                "{$detected} ".class_basename($userModel)." has no assignRole(), so this is a plain factory user: give it role '{$role}' in the acting_as class.",
            );
        }

        return $propose(
            true, "{$user}::factory()->create()->assignRole('{$role}')", $confidence,
            "{$detected} Ensure that role exists (seed it or your TestCase seeds roles).",
        );
    }

    protected function isOpen(string $source): bool
    {
        return preg_match('/return\s+true\s*;/', $source) === 1
            && preg_match('/return\s+false\s*;/', $source) !== 1
            && ! preg_match('/hasRole|hasAnyRole|hasPermission|email|is_admin|isAdmin/i', $source);
    }

    protected function detectRole(string $source): ?string
    {
        if (preg_match('/has(?:Any)?Role\(\s*\[?\s*[\'"]([^\'"]+)[\'"]/', $source, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Look for a role requirement in the tenancy gate (getTenants / canAccessTenant),
     * used when canAccessPanel itself is open but tenant access is role-gated.
     *
     * @param  class-string  $userModel
     */
    protected function detectTenancyRole(string $userModel): ?string
    {
        foreach (['canAccessTenant', 'getTenants'] as $method) {
            $source = $this->methodSource($userModel, $method);

            if ($source !== null && ($role = $this->detectRole($source))) {
                return $role;
            }
        }

        return null;
    }

    protected function detectBooleanFlag(string $source): ?string
    {
        if (preg_match('/->(is_admin|is_active|is_staff)\b/', $source, $m)) {
            return $m[1];
        }

        if (preg_match('/->(isAdmin|isStaff)\(\)/', $source)) {
            return 'is_admin';
        }

        return null;
    }

    protected function detectsEmailGate(string $source): bool
    {
        return preg_match('/email/i', $source) === 1
            && preg_match('/allowed_emails|in_array|str_ends_with|ends_with|@/', $source) === 1;
    }

    protected function detectEmailDomain(string $source): ?string
    {
        if (preg_match('/(@[\w.\-]+)/', $source, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string} [expression, explanation]
     */
    protected function proposeTenant(Panel $panel, ?string $userModel): array
    {
        $tenantModel = $panel->hasTenancy() ? $panel->getTenantModel() : null;

        if ($tenantModel === null) {
            return [null, null];
        }

        $relation = $userModel === null ? null : TenantMembership::relation($tenantModel, $userModel);

        if ($relation !== null) {
            return [null, "The default tenant attaches the acting user via {$relation}()."];
        }

        return [
            '\\'.ltrim($tenantModel, '\\').'::factory()->create()',
            'No many-to-many relation from the tenant to the user model found: the generated class has to attach $user itself.',
        ];
    }

    /**
     * @return class-string|null
     */
    protected function guardModel(string $guard): ?string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if ($provider === null) {
            return null;
        }

        return config("auth.providers.{$provider}.model");
    }

    protected function methodSource(string $class, string $method): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($class);

            if (! $ref->hasMethod($method)) {
                return null;
            }

            $m = $ref->getMethod($method);
            $file = $m->getFileName();

            if ($file === false || $m->getStartLine() === false) {
                return null;
            }

            $lines = file($file);

            if ($lines === false) {
                return null;
            }

            return implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
        } catch (\Throwable) {
            return null;
        }
    }
}
