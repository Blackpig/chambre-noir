<?php

namespace BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages;

use BlackpigCreatif\ChambreNoir\Resources\GalleryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['title'] = ['en' => $data['title_en'] ?? '', 'fr' => $data['title_fr'] ?? ''];

        unset($data['title_en'], $data['title_fr']);

        return $data;
    }
}
