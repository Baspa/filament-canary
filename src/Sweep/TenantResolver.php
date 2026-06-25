<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Support\Factories;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the tenant used to build URLs and scope records for tenant-aware panels.
 *
 * Default: create one via the tenant-model factory. Apps where tenant membership is
 * non-trivial wire a closure through config('filament-canary.tenant').
 */
class TenantResolver
{
    public function resolve(Panel $panel): ?Model
    {
        if ($tenant = $this->fromConfig($panel)) {
            return $tenant;
        }

        $tenantModel = $panel->getTenantModel();

        if ($tenantModel === null || ! Factories::has($tenantModel)) {
            return null;
        }

        return Factories::make($tenantModel);
    }

    protected function fromConfig(Panel $panel): ?Model
    {
        $config = config('filament-canary.tenant');

        $resolver = match (true) {
            is_callable($config) => $config,
            is_array($config) => $config[$panel->getId()] ?? $config['default'] ?? null,
            default => null,
        };

        if (! is_callable($resolver)) {
            return null;
        }

        return $resolver($panel);
    }
}
