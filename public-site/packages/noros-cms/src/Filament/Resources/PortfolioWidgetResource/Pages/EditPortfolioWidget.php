<?php

namespace Noros\Cms\Filament\Resources\PortfolioWidgetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Noros\Cms\Filament\Resources\PortfolioWidgetResource;

class EditPortfolioWidget extends EditRecord
{
    protected static string $resource = PortfolioWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
