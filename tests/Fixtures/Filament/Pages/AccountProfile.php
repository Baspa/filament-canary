<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Panel;

class AccountProfile extends EditProfile
{
    protected static ?string $slug = 'account';

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'account';
    }
}
