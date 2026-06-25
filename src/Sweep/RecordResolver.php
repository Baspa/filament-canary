<?php

namespace Baspa\FilamentCanary\Sweep;

use Baspa\FilamentCanary\Support\Factories;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a record for record-bound pages (Edit/View) via the model's factory.
 * No factory => null, and the sweep skips that page with a visible reason.
 */
class RecordResolver
{
    /**
     * @param  class-string|null  $modelClass
     */
    public function resolve(?string $modelClass): ?Model
    {
        if ($modelClass === null || ! Factories::has($modelClass)) {
            return null;
        }

        return Factories::make($modelClass);
    }
}
