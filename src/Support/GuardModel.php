<?php

namespace Baspa\FilamentCanary\Support;

class GuardModel
{
    public static function for(string $guard): ?string
    {
        $provider = config("auth.guards.{$guard}.provider");

        if (! is_string($provider)) {
            return null;
        }

        $model = config("auth.providers.{$provider}.model");

        return is_string($model) ? $model : null;
    }
}
