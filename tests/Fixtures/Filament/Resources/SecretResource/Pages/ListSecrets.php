<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\SecretResource\Pages;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\SecretResource;
use Filament\Resources\Pages\ListRecords;

class ListSecrets extends ListRecords
{
    protected static string $resource = SecretResource::class;
}
