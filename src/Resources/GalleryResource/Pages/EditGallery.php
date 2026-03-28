<?php

namespace BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages;

use BlackpigCreatif\ChambreNoir\Resources\GalleryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
