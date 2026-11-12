<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TeamPostResource\Pages;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\TeamPostResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamPosts extends ListRecords
{
    protected static string $resource = TeamPostResource::class;
}
