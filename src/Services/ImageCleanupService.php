<?php

namespace BlackpigCreatif\ChambreNoir\Services;

use Illuminate\Support\Collection;

/**
 * Service for cleaning up ChambreNoir image files
 * Used by both model fields (RetouchMediaUpload) and Atelier block attributes
 */
class ImageCleanupService
{
    public function __construct(
        protected ConversionManager $conversionManager
    ) {}

    /**
     * Clean up a single ChambreNoir image
     * Used by RetouchMediaUpload component for model field cleanup
     *
     * @param array $imageData ChambreNoir image data structure
     * @param string $disk Storage disk name
     * @param array $context Additional context for logging (optional)
     * @return bool Success status
     */
    public function cleanupSingleImage(
        array $imageData,
        string $disk = 'public',
        array $context = []
    ): bool {
        try {
            $this->conversionManager->delete($imageData, $disk);

            \Log::debug('ChambreNoir: Cleaned up image', array_merge([
                'original' => $imageData['original'] ?? null,
            ], $context));

            return true;
        } catch (\Exception $e) {
            \Log::error('ChambreNoir: Failed to cleanup image', array_merge([
                'image_data' => $imageData,
                'error' => $e->getMessage(),
            ], $context));

            return false;
        }
    }

    /**
     * Clean up old image files from block attributes before they are deleted
     * Used by Atelier's BlockManager when deleting block attributes
     *
     * @param Collection $attributes Block attributes collection
     * @param string $disk Storage disk name
     * @return int Number of images cleaned up
     */
    public function cleanupOldImages(Collection $attributes, string $disk = 'public'): int
    {
        $cleanedCount = 0;

        foreach ($attributes as $attribute) {
            if ($this->shouldCleanup($attribute)) {
                $imageData = json_decode($attribute->value, true);

                // Use cleanupSingleImage with block context
                $success = $this->cleanupSingleImage($imageData, $disk, [
                    'block_id' => $attribute->block_id,
                    'key' => $attribute->key,
                ]);

                if ($success) {
                    $cleanedCount++;
                }
            }
        }

        return $cleanedCount;
    }

    /**
     * Check if an attribute contains ChambreNoir image data that needs cleanup
     *
     * @param $attribute BlockAttribute model
     * @return bool
     */
    protected function shouldCleanup($attribute): bool
    {
        // Only process if value is a JSON string
        if (!is_string($attribute->value)) {
            return false;
        }

        // Try to decode
        $decoded = json_decode($attribute->value, true);

        // Check if it's ChambreNoir format
        return is_array($decoded)
            && isset($decoded['original'])
            && isset($decoded['conversions'])
            && is_array($decoded['conversions']);
    }
}
