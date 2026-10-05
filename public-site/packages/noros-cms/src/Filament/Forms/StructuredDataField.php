<?php

namespace Noros\Cms\Filament\Forms;

use Filament\Forms\Components\Textarea;

class StructuredDataField
{
    public static function make(string $name = 'structured_data'): Textarea
    {
        return Textarea::make($name)->label(__('noros-cms::admin.additional_structured_data'))->rows(8)->columnSpanFull()
            ->helperText('JSON-LD')->maxLength(100000)->rules(['nullable', 'json', function (): \Closure {
                return function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }
                    try {
                        $data = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $fail('Expected JSON with at most 32 nesting levels.');

                        return;
                    }
                    if (! is_array($data) || (isset($data['@graph']) && (! is_array($data['@graph']) || ! array_is_list($data['@graph'])))) {
                        $fail('Expected a JSON object or array; @graph must be a JSON array.');
                    }
                };
            }])
            ->formatStateUsing(fn (mixed $state): ?string => blank($state) ? null : json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))
            ->dehydrateStateUsing(fn (?string $state): ?array => blank($state) ? null : json_decode($state, true, 32, JSON_THROW_ON_ERROR));
    }
}
