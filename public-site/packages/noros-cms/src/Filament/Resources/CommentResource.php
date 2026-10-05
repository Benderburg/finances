<?php

namespace Noros\Cms\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Noros\Cms\Enums\CommentStatus;
use Noros\Cms\Filament\CmsResource as Resource;
use Noros\Cms\Filament\Resources\CommentResource\Pages;
use Noros\Cms\Models\Comment;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationGroup(): ?string
    {
        return __('noros-cms::engagement.title');
    }

    public static function getModelLabel(): string
    {
        return __('noros-cms::admin.comment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('noros-cms::admin.comments');
    }

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $count = Comment::query()->where('status', CommentStatus::Pending->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Forms\Components\Select::make('post_id')
                ->label(__('noros-cms::admin.article'))
                ->relationship('post', 'title')
                ->searchable()
                ->preload()
                ->required(fn (?Comment $record): bool => ! $record || ! $record->commentable_type || $record->commentable_type === 'post')
                ->disabled(fn (?Comment $record): bool => $record !== null)
                ->visible(fn (?Comment $record): bool => ! $record || ! $record->commentable_type || $record->commentable_type === 'post')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('commentable_type')->label(__('noros-cms::admin.content_type'))->disabled()->dehydrated(false),
            Forms\Components\TextInput::make('commentable_id')->label(__('noros-cms::admin.content_id'))->disabled()->dehydrated(false),
            Forms\Components\Select::make('parent_id')
                ->label(__('noros-cms::admin.reply_to_comment'))
                ->relationship('parent', 'author_name')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('status')
                ->label(__('noros-cms::admin.status'))
                ->options(CommentStatus::options())
                ->default(CommentStatus::Approved->value)
                ->required(),
            Forms\Components\TextInput::make('author_name')
                ->label(__('noros-cms::admin.author_name'))
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('author_email')
                ->label(__('noros-cms::admin.ui_email'))
                ->email()
                ->required()
                ->maxLength(190),
            Forms\Components\TextInput::make('author_website')
                ->label(__('noros-cms::admin.website'))
                ->url()
                ->maxLength(255),
            Forms\Components\Textarea::make('body')
                ->label(__('noros-cms::admin.comment_d89b31'))
                ->required()
                ->rows(7)
                ->maxLength(5000)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('ip_address')
                ->label(__('noros-cms::admin.ip_address'))
                ->disabled()
                ->dehydrated(false),
            Forms\Components\Textarea::make('user_agent')
                ->label(__('noros-cms::admin.ui_user_agent'))
                ->disabled()
                ->dehydrated(false)
                ->rows(2),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('author_name')
                    ->label(__('noros-cms::admin.author'))
                    ->searchable()
                    ->description(fn (Comment $record): string => $record->author_email),
                Tables\Columns\TextColumn::make('body')
                    ->label(__('noros-cms::admin.comment_d89b31'))
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('post.title')
                    ->label(__('noros-cms::admin.article'))
                    ->searchable()
                    ->limit(45)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('commentable_type')->label(__('noros-cms::admin.content_type'))->badge()->toggleable(),
                Tables\Columns\TextColumn::make('commentable_id')->label(__('noros-cms::admin.content_id'))->toggleable(),
                Tables\Columns\TextColumn::make('rating.score')->label(__('noros-cms::engagement.score')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('noros-cms::admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (CommentStatus $state): string => $state->label())
                    ->color(fn (CommentStatus $state): string => match ($state) {
                        CommentStatus::Approved => 'success',
                        CommentStatus::Pending => 'warning',
                        CommentStatus::Spam => 'danger',
                        CommentStatus::Rejected => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('noros-cms::admin.submitted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('commentable_type')->label(__('noros-cms::admin.content_type'))
                    ->options(['post' => __('noros-cms::engagement.blog'), 'product' => __('noros-cms::engagement.shop')]),
                Tables\Filters\Filter::make('reviews')->label(__('noros-cms::engagement.reviews'))
                    ->query(fn ($query) => $query->whereHas('rating')),
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('noros-cms::admin.status'))
                    ->options(CommentStatus::options()),
                Tables\Filters\SelectFilter::make('post')
                    ->label(__('noros-cms::admin.article'))
                    ->relationship('post', 'title')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label(__('noros-cms::admin.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Comment $record): bool => $record->status !== CommentStatus::Approved)
                    ->requiresConfirmation()
                    ->authorize(fn (): bool => Gate::allows('moderate_comments'))
                    ->action(fn (Comment $record) => $record->update(['status' => CommentStatus::Approved])),
                Action::make('spam')
                    ->label(__('noros-cms::admin.mark_as_spam'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Comment $record): bool => $record->status !== CommentStatus::Spam)
                    ->authorize(fn (): bool => Gate::allows('moderate_comments'))
                    ->action(fn (Comment $record) => $record->update(['status' => CommentStatus::Spam])),
                Action::make('reject')->label(__('noros-cms::admin.rejected'))
                    ->authorize(fn (): bool => Gate::allows('moderate_comments'))
                    ->requiresConfirmation()->action(fn (Comment $record) => $record->update(['status' => CommentStatus::Rejected])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label(__('noros-cms::admin.approve'))
                        ->authorize(fn (): bool => Gate::allows('moderate_comments'))
                        ->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['status' => CommentStatus::Approved]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'create' => Pages\CreateComment::route('/create'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
