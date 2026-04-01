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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextInputColumn;
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

            TextInput::make('caption')
                ->nullable(),
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

                TextInputColumn::make('caption')
                    ->placeholder('No caption'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
