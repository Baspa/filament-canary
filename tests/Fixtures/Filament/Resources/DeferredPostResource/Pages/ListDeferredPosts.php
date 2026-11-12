<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\DeferredPostResource\Pages;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\DeferredPostResource;
use Filament\Resources\Pages\ListRecords;

class ListDeferredPosts extends ListRecords
{
    protected static string $resource = DeferredPostResource::class;
}
