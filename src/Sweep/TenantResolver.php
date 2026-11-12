<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Support\ConfiguredResolver;
use Baspa\FilamentCanary\Support\Factories;
use Baspa\FilamentCanary\Support\TenantMembership;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use UnexpectedValueException;

/**
 * Resolves the tenant used to build URLs and scope records for tenant-aware panels.
 *
 * Default: create one via the tenant-model factory and, when the tenant model has a
 * many-to-many relation to the user model, attach the acting user to it. Apps where
 * tenant membership is non-trivial wire a resolver through config('filament-canary.tenant');
 * it receives the acting user so it can make that user a member of the tenant.
 */
class TenantResolver
{
    public function resolve(Panel $panel, Authenticatable $user): ?Model
    {
        $resolver = ConfiguredResolver::find('filament-canary.tenant', $panel);
        $tenant = $resolver === null ? null : $resolver($panel, $user);

        if ($tenant === null) {
            return $this->fromFactory($panel, $user);
        }

        if (! $tenant instanceof Model) {
            throw new UnexpectedValueException(
                "[filament-canary.tenant] resolver for panel [{$panel->getId()}] must return an Eloquent model, got [".get_debug_type($tenant).'].'
            );
        }

        return $tenant;
    }

    protected function fromFactory(Panel $panel, Authenticatable $user): ?Model
    {
        $tenantModel = $panel->getTenantModel();

        if ($tenantModel === null || ! Factories::has($tenantModel)) {
            return null;
        }

        $tenant = Factories::make($tenantModel);
        $relation = $user instanceof Model ? TenantMembership::relation($tenantModel, $user::class) : null;
        $members = $relation === null ? null : $tenant?->{$relation}();

        if ($members instanceof BelongsToMany) {
            $members->attach($user);
        }

        return $tenant;
    }
}
