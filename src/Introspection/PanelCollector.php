<?php

namespace Baspa\FilamentCanary\Introspection;

use Filament\Facades\Filament;
use Filament\Panel;

class PanelCollector
{
    /**
     * @param  list<string>  $only  Only these panel ids (empty = all)
     * @param  list<string>  $except  Skip these panel ids
     * @return list<Panel>
     */
    public function collect(array $only = [], array $except = []): array
    {
        $panels = array_values(Filament::getPanels());

        return array_values(array_filter($panels, function (Panel $panel) use ($only, $except) {
            $id = $panel->getId();

            if ($only !== [] && ! in_array($id, $only, true)) {
                return false;
            }

            return ! in_array($id, $except, true);
        }));
    }
}
