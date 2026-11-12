<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Baspa\FilamentCanary\Tests\Fixtures\Models\Concerns\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PermissionTenantUser extends Authenticatable implements FilamentUser
{
    use HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->hasRole('owner');
    }
}
