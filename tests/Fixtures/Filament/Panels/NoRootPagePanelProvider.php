<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Panels;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource;
use Filament\Panel;
use Filament\PanelProvider;

class NoRootPagePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('no-root-page')
            ->path('no-root-page')
            ->login()
            ->resources([
                PostResource::class,
            ]);
    }
}
