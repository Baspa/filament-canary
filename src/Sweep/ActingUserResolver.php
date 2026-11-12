<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Support\ConfiguredResolver;
use Baspa\FilamentCanary\Support\Factories;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Produces the "authorized" user used to assert that a panel's pages mount.
 *
 * By default it creates a user via the guard's provider-model factory. Apps where
 * panel access requires a specific user (a role, a flag) wire a resolver through
 * config('filament-canary.acting_as') — the one bounded piece of setup.
 */
class ActingUserResolver
{
    public function resolve(Panel $panel): ?Authenticatable
    {
        $resolver = ConfiguredResolver::find('filament-canary.acting_as', $panel);

        if ($resolver !== null && ($user = $resolver($panel))) {
            return $user;
        }

        return $this->fromFactory($panel);
    }

    protected function fromFactory(Panel $panel): ?Authenticatable
    {
        $modelClass = $this->guardModel($panel->getAuthGuard());

        if ($modelClass === null || ! Factories::has($modelClass)) {
            return null;
        }

        $user = Factories::make($modelClass);

        return $user instanceof Authenticatable ? $user : null;
    }

    protected function guardModel(string $guard): ?string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider)) {
            return null;
        }

        $model = config("auth.providers.{$provider}.model");

        return is_string($model) ? $model : null;
    }
}
