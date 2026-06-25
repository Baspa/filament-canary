<?php

namespace Baspa\FilamentCanary\Install;

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
        $tenantExpression = $this->tenantExpression($panel);

        if ($userModel === null) {
            return new AccessProposal(
                $panel->getId(), $panel->getAuthGuard(), null, true, null, $tenantExpression,
                'none', "Could not resolve a user model for guard [{$panel->getAuthGuard()}]; set acting_as manually.",
            );
        }

        $source = $this->methodSource($userModel, 'canAccessPanel');
        $user = '\\'.ltrim($userModel, '\\');

        // No panel gate / explicitly open. The default factory user passes canAccessPanel —
        // but for a tenant panel the real gate often lives in getTenants/canAccessTenant,
        // so look there before declaring the panel open.
        if ($source === null || $this->isOpen($source)) {
            if ($panel->hasTenancy() && ($tenantRole = $this->detectTenancyRole($userModel))) {
                return new AccessProposal(
                    $panel->getId(), $panel->getAuthGuard(), $userModel, true,
                    "fn (\\Filament\\Panel \$panel) => {$user}::factory()->create()->assignRole('{$tenantRole}')",
                    $tenantExpression, 'medium',
                    "canAccessPanel() is open, but tenant access requires role '{$tenantRole}' (from getTenants/canAccessTenant). Ensure that role exists.",
                );
            }

            return new AccessProposal(
                $panel->getId(), $panel->getAuthGuard(), $userModel, false, null, $tenantExpression,
                'high', $source === null
                    ? 'No canAccessPanel() override found; a plain factory user should work.'
                    : 'canAccessPanel() returns true; a plain factory user should work.',
            );
        }

        if ($role = $this->detectRole($source)) {
            return new AccessProposal(
                $panel->getId(), $panel->getAuthGuard(), $userModel, true,
                "fn (\\Filament\\Panel \$panel) => {$user}::factory()->create()->assignRole('{$role}')",
                $tenantExpression, 'high',
                "Role-based access detected (assignRole('{$role}')). Ensure that role exists (seed it or your TestCase seeds roles).",
            );
        }

        if ($column = $this->detectBooleanFlag($source)) {
            return new AccessProposal(
                $panel->getId(), $panel->getAuthGuard(), $userModel, true,
                "fn (\\Filament\\Panel \$panel) => {$user}::factory()->create(['{$column}' => true])",
                $tenantExpression, 'medium',
                "Boolean flag access detected ([{$column}]). Adjust if your factory needs a different value.",
            );
        }

        if ($this->detectsEmailGate($source)) {
            $domain = $this->detectEmailDomain($source);
            $email = $domain ? "admin{$domain}" : 'admin@example.test';

            return new AccessProposal(
                $panel->getId(), $panel->getAuthGuard(), $userModel, true,
                "fn (\\Filament\\Panel \$panel) => {$user}::factory()->create(['email' => '{$email}'])",
                $tenantExpression, 'medium',
                'Email-based access detected. Replace the email with one your allowlist accepts.',
            );
        }

        return new AccessProposal(
            $panel->getId(), $panel->getAuthGuard(), $userModel, true,
            "fn (\\Filament\\Panel \$panel) => {$user}::factory()->create()",
            $tenantExpression, 'low',
            "Could not read the access gate confidently; using a plain factory user. Adjust if this panel's pages come back as needs-auth.",
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

    protected function tenantExpression(Panel $panel): ?string
    {
        if (! $panel->hasTenancy()) {
            return null;
        }

        $tenantModel = $panel->getTenantModel();

        if ($tenantModel === null) {
            return null;
        }

        return 'fn (\\Filament\\Panel $panel) => \\'.ltrim($tenantModel, '\\').'::factory()->create()';
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
