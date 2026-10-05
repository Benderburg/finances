<?php

namespace Noros\Core\Filament\Resources;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        return Gate::inspect('manage_roles');
    }

    public static function getModelLabel(): string
    {
        return __('noros-core::admin.ui_role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-core::admin.ui_roles');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.ui_platform');
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [
            TextInput::make('name')->label(__('noros-core::admin.ui_name'))->required()->maxLength(255)->unique(ignoreRecord: true),
            CheckboxList::make('permissions')->label(__('noros-core::admin.ui_permissions'))->options(collect(config('noros.permissions'))->mapWithKeys(fn (string $permission): array => [$permission => __('noros-core::admin.permission_'.$permission)])->all())
                ->default([])->rules(['array'])->in(config('noros.permissions')),
        ];

        return $schema->columns(1)->components($fields);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('noros-core::admin.ui_name'))->searchable()->sortable(),
            TextColumn::make('permissions')->label(__('noros-core::admin.ui_permissions'))->formatStateUsing(fn (string $state): string => __('noros-core::admin.permission_'.$state))->badge(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => RoleResource\Pages\ManageRoles::route('/')];
    }
}
