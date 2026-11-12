<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Panels;

use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class MultiDomainAuthPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('multi-domain-auth')
            ->path('multi-domain-auth')
            ->domains(['one.canary.test', 'two.canary.test'])
            ->login()
            ->emailVerification()
            ->multiFactorAuthentication([EmailAuthentication::make()], isRequired: true)
            ->profile()
            ->pages([
                Dashboard::class,
            ]);
    }
}
