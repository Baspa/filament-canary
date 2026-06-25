<?php

namespace Baspa\FilamentCanary\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factories
{
    /**
     * Whether the given model class can produce records via a factory.
     */
    public static function has(string $modelClass): bool
    {
        return is_subclass_of($modelClass, Model::class)
            && in_array(HasFactory::class, class_uses_recursive($modelClass), true);
    }

    /**
     * Create a record via the model's factory. Returns null only when there is no
     * factory to call; any error raised while creating the record is allowed to
     * propagate so the caller can surface the real reason instead of hiding it.
     */
    public static function make(string $modelClass): ?Model
    {
        // method_exists narrows the type so PHPStan accepts the static call, and a
        // model's own newFactory() override is still respected.
        if (! method_exists($modelClass, 'factory')) {
            return null;
        }

        $factory = $modelClass::factory();

        if (! $factory instanceof Factory) {
            return null;
        }

        $record = $factory->create();

        return $record instanceof Model ? $record : null;
    }
}
