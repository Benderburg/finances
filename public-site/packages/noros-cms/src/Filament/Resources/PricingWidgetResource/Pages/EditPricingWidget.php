<?php

namespace Noros\Cms\Filament\Resources\PricingWidgetResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Noros\Cms\Filament\Resources\PricingWidgetResource;

class EditPricingWidget extends EditRecord
{
    protected static string $resource = PricingWidgetResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
