<?php

namespace Baspa\FilamentCanary\Support;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factories
{
    /**
     * Whether the given model class can produce records via a factory.
     *
     * @param  class-string  $modelClass
     */
    public static function has(string $modelClass): bool
    {
        return is_subclass_of($modelClass, Model::class)
            && in_array(HasFactory::class, class_uses_recursive($modelClass), true);
    }

    /**
     * @param  class-string  $modelClass
     */
    public static function make(string $modelClass): ?Model
    {
        try {
            // Dynamic callable so PHPStan doesn't need HasFactory on the base Model,
            // and so a model's own newFactory() override is respected.
            $factory = call_user_func([$modelClass, 'factory']);

            if (! $factory instanceof Factory) {
                return null;
            }

            $record = $factory->create();

            return $record instanceof Model ? $record : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
