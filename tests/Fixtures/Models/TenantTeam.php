<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Models;

use Baspa\FilamentCanary\Tests\Fixtures\Database\Factories\TenantTeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantTeam extends Model
{
    use HasFactory;

    protected $table = 'teams';

    protected $guarded = [];

    public $timestamps = false;

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class, 'team_user', 'team_id', 'user_id');
    }

    protected static function newFactory(): TenantTeamFactory
    {
        return TenantTeamFactory::new();
    }
}
