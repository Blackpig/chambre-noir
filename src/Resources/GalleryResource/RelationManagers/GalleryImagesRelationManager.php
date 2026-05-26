<?php

namespace BlackpigCreatif\ChambreNoir\Resources\GalleryResource\RelationManagers;

use BlackpigCreatif\ChambreNoir\Forms\Components\RetouchMediaUpload;
use BlackpigCreatif\ChambreNoir\Models\Gallery;
use BlackpigCreatif\ChambreNoir\Models\GalleryImage;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GalleryImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        /** @var Gallery $gallery */
        $gallery = $this->getOwnerRecord();

        $conversionKey = $gallery->conversion;
        $conversionClass = $conversionKey
            ? config("chambre-noir.conversions.{$conversionKey}.class")
            : null;

        $disk = config('chambre-noir.disk', 'public');

        return $schema->components([
            RetouchMediaUpload::make('image')
                ->disk($disk)
                ->visibility('public')
                ->when(
                    $conversionClass !== null,
                    fn (RetouchMediaUpload $field): RetouchMediaUpload => $field->preset($conversionClass)
                )
                ->directory("galleries/{$gallery->slug}")
                ->required()
                ->columnSpanFull(),

            Tabs::make('Locales')
                ->tabs([
                    Tab::make('English')
                        ->schema([
                            TextInput::make('caption_en')
                                ->label('Caption')
                                ->nullable(),
                        ]),
                    Tab::make('Français')
                        ->schema([
                            TextInput::make('caption_fr')
                                ->label('Caption')
                                ->nullable(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->getStateUsing(fn (GalleryImage $record): ?string => $record->getMediaUrl('image', 'thumb')
                    )
                    ->square()
                    ->size(60),

                TextColumn::make('caption')
                    ->getStateUsing(fn (GalleryImage $record): string => $record->getTranslation('caption', 'en', false) ?: '')
                    ->placeholder('No caption'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['caption'] = ['en' => $data['caption_en'] ?? null, 'fr' => $data['caption_fr'] ?? null];

                        unset($data['caption_en'], $data['caption_fr']);

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, GalleryImage $record): array {
                        $data['caption_en'] = $record->getTranslation('caption', 'en', false);
                        $data['caption_fr'] = $record->getTranslation('caption', 'fr', false);

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['caption'] = ['en' => $data['caption_en'] ?? null, 'fr' => $data['caption_fr'] ?? null];

                        unset($data['caption_en'], $data['caption_fr']);

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
