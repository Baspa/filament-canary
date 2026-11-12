<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Baspa\FilamentCanary\Tests\Fixtures\Models\Concerns\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PermissionRoleUser extends Authenticatable implements FilamentUser
{
    use HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
}
