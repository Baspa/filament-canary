<?php

namespace Baspa\FilamentCanary\Sweep;

use RuntimeException;

class EnvironmentNotAllowedException extends RuntimeException
{
    public static function production(string $environment): self
    {
        return new self(
            "filament-canary never runs in production, and APP_ENV [{$environment}] is a production name ".
            '(production, prod, prd and live are always refused, whatever filament-canary.allowed_environments says). '.
            'The sweep requests every page as an authenticated user, which can trigger real side effects (queued jobs, '.
            "mail, audit logs, writes on other connections) that a database rollback won't undo."
        );
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function notListed(string $environment, array $allowed): self
    {
        $environments = implode(', ', array_map(
            fn (string $name) => var_export($name, true),
            [...$allowed, $environment],
        ));

        return new self(
            "filament-canary refuses to run with APP_ENV [{$environment}], because ".
            "filament-canary.allowed_environments doesn't list it. To run the sweep here, set ".
            "'allowed_environments' => [{$environments}] in config/filament-canary.php ".
            '(publish it first with `php artisan vendor:publish --tag=filament-canary-config` if you haven\'t).'
        );
    }

    public static function invalidConfig(mixed $value): self
    {
        $given = is_array($value) ? 'an array that is not a list of strings' : get_debug_type($value);

        return new self(
            'filament-canary.allowed_environments must be a list of APP_ENV names or patterns, '.
            "such as ['local', 'testing', 'ci-*'], got {$given}."
        );
    }
}
