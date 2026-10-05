<?php

namespace Noros\Cms\Filament\Resources\PortfolioWidgetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Noros\Cms\Filament\Resources\PortfolioWidgetResource;

class ListPortfolioWidgets extends ListRecords
{
    protected static string $resource = PortfolioWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
