<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasManyUsersTeam extends Model
{
    protected $table = 'teams';

    public function users(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_id');
    }
}
