<?php

namespace Noros\Cms\Filament\Resources\PricingWidgetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Noros\Cms\Filament\Resources\PricingWidgetResource;

class ListPricingWidgets extends ListRecords
{
    protected static string $resource = PricingWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
