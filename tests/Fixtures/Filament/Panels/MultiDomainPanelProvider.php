<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Panels;

use Baspa\FilamentCanary\Tests\Fixtures\Filament\Pages\AccountProfile;
use Baspa\FilamentCanary\Tests\Fixtures\Filament\Resources\PostResource;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class MultiDomainPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('multi-domain')
            ->path('multi-domain')
            ->domains(['one.canary.test', 'two.canary.test'])
            ->login()
            ->emailVerification()
            ->multiFactorAuthentication([EmailAuthentication::make()], isRequired: true)
            ->profile(AccountProfile::class)
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                PostResource::class,
            ]);
    }
}
