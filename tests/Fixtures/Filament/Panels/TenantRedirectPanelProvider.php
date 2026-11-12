<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Panels;

use Baspa\FilamentCanary\Tests\Fixtures\Models\Team;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class TenantRedirectPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tenant-redirect')
            ->path('tenant-redirect')
            ->login()
            ->tenant(Team::class)
            ->pages([
                Dashboard::class,
            ]);
    }
}
