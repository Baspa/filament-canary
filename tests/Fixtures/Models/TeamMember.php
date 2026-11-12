<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Baspa\FilamentCanary\Tests\Fixtures\Database\Factories\TeamMemberFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;

class TeamMember extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(TenantTeam::class, 'team_user', 'user_id', 'team_id');
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return $this->teams;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams()->whereKey($tenant->getKey())->exists();
    }

    protected static function newFactory(): TeamMemberFactory
    {
        return TeamMemberFactory::new();
    }
}
