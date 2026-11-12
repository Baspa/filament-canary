<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Resolvers;

use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantTeam;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;

class AttachingTeamTenant
{
    public function __invoke(Panel $panel, Authenticatable $user): TenantTeam
    {
        return TenantTeam::factory()->hasAttached($user, [], 'members')->create();
    }
}
