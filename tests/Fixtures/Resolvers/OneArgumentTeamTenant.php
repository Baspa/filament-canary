<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Resolvers;

use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Baspa\FilamentCanary\Tests\Fixtures\Models\TenantTeam;
use Filament\Panel;

class OneArgumentTeamTenant
{
    public function __invoke(Panel $panel): TenantTeam
    {
        $team = TenantTeam::factory()->create();
        $team->members()->attach(TeamMember::query()->pluck('id'));

        return $team;
    }
}
