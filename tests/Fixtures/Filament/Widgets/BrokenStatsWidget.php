<?php

namespace Baspa\FilamentCanary\Tests\Fixtures\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use RuntimeException;

class BrokenStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        throw new RuntimeException('widget query broke');
    }
}
