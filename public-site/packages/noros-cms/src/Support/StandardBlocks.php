<?php

namespace Noros\Cms\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class StandardBlocks
{
    public function register(BlockRegistry $registry): void
    {
        foreach (['text_image', 'gallery', 'advantages', 'services', 'cta', 'contacts', 'buttons', 'faq', 'clients', 'reviews', 'latest_posts', 'contact_form'] as $type) {
            $registry->register($type, 'noros-cms::blocks.'.$type, fn (): array => $this->schema($type));
        }
    }

    public function schema(string $type): array
    {
        $fields = [Toggle::make('enabled')->label(__('noros-cms::blocks.enabled'))->default(true), $this->text('title')];
        $items = match ($type) {
            'faq' => [$this->text('question')->required(), $this->paragraph('answer')->required()],
            'gallery' => [$this->image()->required(), $this->text('alt'), $this->text('caption')],
            'advantages', 'services' => [$this->text('title')->required(), $this->paragraph('text'), $this->image(), $this->text('alt'), $this->link('url')],
            'clients' => [$this->text('name')->required(), $this->image(), $this->text('alt'), $this->link('url')],
            'reviews' => [$this->text('name')->required(), $this->paragraph('quote')->required(), $this->text('role')],
            'buttons' => [$this->text('label')->required(), $this->link('url')->required()],
            default => [],
        };
        if ($items !== []) {
            $fields[] = Repeater::make('items')->label(__('noros-cms::blocks.items'))->schema($items)->minItems(1)->maxItems(50)->defaultItems(1)->cloneable()->reorderableWithButtons()->collapsible();
        }
        if (in_array($type, ['text_image', 'cta', 'contacts', 'contact_form'], true)) {
            $fields[] = $this->paragraph('text');
        }
        if ($type === 'text_image') {
            $fields[] = $this->image()->required();
            $fields[] = $this->text('alt');
        }
        if ($type === 'cta') {
            $fields[] = $this->text('label')->required();
            $fields[] = $this->link('url')->required();
        }
        if ($type === 'contacts') {
            $fields[] = $this->text('email')->email();
            $fields[] = $this->text('phone')->tel();
            $fields[] = $this->paragraph('address');
        }
        if ($type === 'latest_posts') {
            $fields[] = TextInput::make('limit')->label(__('noros-cms::blocks.limit'))->integer()->minValue(1)->maxValue(12)->required()->default(3);
        }

        return $fields;
    }

    private function text(string $name): TextInput
    {
        return TextInput::make($name)->label(__('noros-cms::blocks.'.$name))->maxLength(255);
    }

    private function paragraph(string $name): Textarea
    {
        return Textarea::make($name)->label(__('noros-cms::blocks.'.$name))->rows(4)->maxLength(10000);
    }

    private function image(): FileUpload
    {
        return FileUpload::make('image')->label(__('noros-cms::blocks.image'))->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->maxSize(10240)->disk('public')->directory('cms/blocks');
    }

    private function link(string $name): TextInput
    {
        return $this->text($name)->regex('~^(?:https?://|mailto:|tel:|/|#)[^\r\n]*$~')->helperText(__('noros-cms::blocks.link_hint'));
    }
}
