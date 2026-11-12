<?php

namespace Baspa\FilamentCanary\Support;

use Filament\Panel;
use InvalidArgumentException;

/**
 * Reads a resolver option such as `acting_as` or `tenant`: a callable, an invokable class
 * name, or an array of those keyed by panel id with an optional 'default' entry.
 */
class ConfiguredResolver
{
    public static function find(string $configKey, Panel $panel): ?callable
    {
        $value = config($configKey);

        if (is_array($value) && ! is_callable($value)) {
            $value = $value[$panel->getId()] ?? $value['default'] ?? null;
        }

        if ($value === null || is_callable($value)) {
            return $value;
        }

        if (is_string($value) && class_exists($value)) {
            $resolver = app($value);

            if (is_callable($resolver)) {
                return $resolver;
            }
        }

        $given = is_string($value) ? $value : get_debug_type($value);

        throw new InvalidArgumentException(
            "[{$configKey}] for panel [{$panel->getId()}] must be a callable or an invokable class name, got [{$given}]."
        );
    }
}
