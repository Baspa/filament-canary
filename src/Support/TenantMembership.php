<?php

namespace Baspa\FilamentCanary\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class TenantMembership
{
    /**
     * The tenant model's many-to-many relation to the user model (for example `members()`),
     * found by the names apps commonly use, or null when there is none.
     */
    public static function relation(string $tenantModel, string $userModel): ?string
    {
        if (! is_subclass_of($tenantModel, Model::class)) {
            return null;
        }

        $tenant = new $tenantModel;
        $candidates = array_unique([Str::camel(Str::plural(class_basename($userModel))), 'members', 'users']);

        foreach ($candidates as $name) {
            if (! method_exists($tenant, $name)) {
                continue;
            }

            try {
                $relation = $tenant->{$name}();
            } catch (\Throwable) {
                continue;
            }

            if ($relation instanceof BelongsToMany && is_a($userModel, $relation->getRelated()::class, true)) {
                return $name;
            }
        }

        return null;
    }
}
