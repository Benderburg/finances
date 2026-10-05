<?php

namespace Noros\Core\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Noros\Core\Filament\Resources\UserResource\Pages;
use Noros\Core\Models\User;

class UserResource extends Resource
{
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        $actor = auth()->user();
        if ($record instanceof User && $actor instanceof User && ! $actor->can('manage_roles') && $record->getKey() !== $actor->getKey()) {
            foreach (config('noros.permissions') as $permission) {
                if ($record->hasPermission($permission) && ! $actor->hasPermission($permission)) {
                    return Response::deny();
                }
            }
        }
        if (in_array($action, ['delete', 'forceDelete'], true) && $record?->getKey() === auth()->id()) {
            return Response::deny();
        }

        return Gate::inspect('manage_users');
    }

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-core::admin.blog');
    }

    public static function getModelLabel(): string
    {
        return __('noros-core::admin.author_e98957');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-core::admin.authors');
    }

    protected static ?int $navigationSort = 50;

    public static function form(Schema $form): Schema
    {
        return $form->columns(1)->schema([
            Section::make(__('noros-core::admin.author_profile'))
                ->schema([
                    Forms\Components\FileUpload::make('avatar')
                        ->label(__('noros-core::admin.avatar'))
                        ->image()
                        ->avatar()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('authors')
                        ->visibility('public'),
                    Forms\Components\TextInput::make('name')
                        ->label(__('noros-core::admin.name'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('position')
                        ->label(__('noros-core::admin.position'))
                        ->maxLength(255),
                    Forms\Components\Textarea::make('bio')
                        ->label(__('noros-core::admin.short_biography'))
                        ->rows(5)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('website')
                        ->label(__('noros-core::admin.website'))
                        ->url()
                        ->maxLength(255),
                    Forms\Components\KeyValue::make('social_links')
                        ->label(__('noros-core::admin.social_networks'))
                        ->keyLabel(__('noros-core::admin.network'))
                        ->valueLabel(__('noros-core::admin.link'))
                        ->addActionLabel(__('noros-core::admin.add_social_network'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('noros-core::admin.account'))
                ->schema([
                    Forms\Components\Select::make('roles')->label(__('noros-core::admin.ui_roles'))->relationship('roles', 'name')->multiple()->preload()
                        ->visible(fn (): bool => auth()->user()?->can('manage_roles') ?? false)
                        ->saveRelationshipsUsing(function (User $record, ?array $state): void {
                            Gate::authorize('manage_roles');
                            $record->roles()->sync($state ?? []);
                        }),
                    Forms\Components\TextInput::make('email')
                        ->label(__('noros-core::admin.ui_email'))
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password')
                        ->label(__('noros-core::admin.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->minLength(8)
                        ->helperText(__('noros-core::admin.leave_empty_when_editing_to_keep_the_current_password')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('noros-core::admin.name'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->position),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('noros-core::admin.ui_email'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('noros-core::admin.updated_8e6cd0'))
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
