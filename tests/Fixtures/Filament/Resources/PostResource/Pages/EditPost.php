<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource\Pages;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;
}
