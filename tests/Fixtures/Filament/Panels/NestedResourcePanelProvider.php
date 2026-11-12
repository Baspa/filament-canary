<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Panels;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\CommentedPostResource;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostCommentResource;
use Filament\Panel;
use Filament\PanelProvider;

class NestedResourcePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('nested-resource')
            ->path('nested-resource')
            ->login()
            ->resources([
                CommentedPostResource::class,
                PostCommentResource::class,
            ]);
    }
}
