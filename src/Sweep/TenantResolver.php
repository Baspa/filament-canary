<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Support\ConfiguredResolver;
use Baspa\FilamentCanary\Support\Factories;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the tenant used to build URLs and scope records for tenant-aware panels.
 *
 * Default: create one via the tenant-model factory. Apps where tenant membership is
 * non-trivial wire a resolver through config('filament-canary.tenant'); it receives
 * the acting user so it can make that user a member of the tenant.
 */
class TenantResolver
{
    public function resolve(Panel $panel, Authenticatable $user): ?Model
    {
        $resolver = ConfiguredResolver::find('filament-canary.tenant', $panel);

        if ($resolver !== null && ($tenant = $resolver($panel, $user))) {
            return $tenant;
        }

        $tenantModel = $panel->getTenantModel();

        if ($tenantModel === null || ! Factories::has($tenantModel)) {
            return null;
        }

        return Factories::make($tenantModel);
    }
}
