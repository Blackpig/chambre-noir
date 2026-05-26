<?php

namespace BlackpigCreatif\ChambreNoir\Resources\GalleryResource\Pages;

use BlackpigCreatif\ChambreNoir\Models\Gallery;
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Gallery $record */
        $record = $this->getRecord();

        $data['title_en'] = $record->getTranslation('title', 'en', false);
        $data['title_fr'] = $record->getTranslation('title', 'fr', false);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['title'] = ['en' => $data['title_en'] ?? '', 'fr' => $data['title_fr'] ?? ''];

        unset($data['title_en'], $data['title_fr']);

        return $data;
    }
}
