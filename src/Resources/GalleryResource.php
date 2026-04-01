<?php

namespace BlackpigCreatif\ChambreNoir\Resources;

use BlackpigCreatif\ChambreNoir\Models\Gallery;
use BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages\CreateGallery;
use BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages\EditGallery;
use BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages\ListGalleries;
use BlackpigCreatif\ChambreNoir\Resources\GalleryResource\RelationManagers\GalleryImagesRelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))
                ),

            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true),

            Textarea::make('description')
                ->nullable()
                ->columnSpanFull(),

            Select::make('conversion')
                ->options(static::conversionOptions())
                ->nullable()
                ->searchable(),

            Toggle::make('is_active')
                ->label('Active'),

            TextInput::make('panel')
                ->hidden(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable(),

                TextColumn::make('conversion')
                    ->formatStateUsing(fn (?string $state): string => $state ? (config("chambre-noir.conversions.{$state}.label", $state)) : '—'
                    ),

                TextColumn::make('images_count')
                    ->counts('images')
                    ->label('Images'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('created_at')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GalleryImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }

    protected static function conversionOptions(): array
    {
        return array_map(
            fn (array $conversion): string => $conversion['label'],
            config('chambre-noir.conversions', [])
        );
    }
}
