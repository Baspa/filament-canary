<?php

namespace Baspa\FilamentCanary\Sweep;

use RuntimeException;

class EnvironmentNotAllowedException extends RuntimeException
{
    public static function production(): self
    {
        return new self(
            'filament-canary refuses to run in the [production] environment, even when '.
            'filament-canary.allowed_environments lists it. The sweep requests every page as an '.
            'authenticated user, which can trigger real side effects (queued jobs, mail, audit logs, '.
            "writes on other connections) that a database rollback won't undo."
        );
    }

    /**
     * @param  array<mixed>  $allowed
     */
    public static function notListed(string $environment, array $allowed): self
    {
        $environments = array_map(
            fn (mixed $name) => var_export($name, true),
            [...array_values($allowed), $environment],
        );

        return new self(
            "filament-canary refuses to run with APP_ENV [{$environment}], because ".
            "filament-canary.allowed_environments doesn't list it. To run the sweep here, set ".
            "'allowed_environments' => [".implode(', ', $environments).'] in config/filament-canary.php '.
            '(publish it first with `php artisan vendor:publish --tag=filament-canary-config` if you haven\'t).'
        );
    }
}
