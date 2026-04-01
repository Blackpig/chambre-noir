<?php

namespace BlackpigCreatif\ChambreNoir\Jobs;

use BlackpigCreatif\ChambreNoir\Services\ImageCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupGalleryImages implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<int, array{original: string, conversions: array<string, string>}> $imagesData
     */
    public function __construct(
        public readonly array $imagesData,
        public readonly string $disk,
        public readonly int $galleryId,
    ) {}

    public function handle(ImageCleanupService $cleanupService): void
    {
        foreach ($this->imagesData as $imageData) {
            if (! is_array($imageData) || ! isset($imageData['original'])) {
                continue;
            }

            $cleanupService->cleanupSingleImage($imageData, $this->disk, [
                'action'     => 'gallery_deleted',
                'gallery_id' => $this->galleryId,
            ]);
        }
    }
}
