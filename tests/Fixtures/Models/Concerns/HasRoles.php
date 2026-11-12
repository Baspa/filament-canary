<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models\Concerns;

/**
 * Stands in for spatie/laravel-permission's HasRoles, which the analyzer recognises by assignRole().
 */
trait HasRoles
{
    public function assignRole(string $role): static
    {
        return $this;
    }

    public function hasRole(string $role): bool
    {
        return false;
    }
}
