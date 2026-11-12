<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Resolvers;

use Baspa\FilamentCanary\Tests\Fixtures\Models\TeamMember;
use Filament\Panel;

class TeamMemberActingAs
{
    public function __invoke(Panel $panel): TeamMember
    {
        return TeamMember::factory()->create();
    }
}
