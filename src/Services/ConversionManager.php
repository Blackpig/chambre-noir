<?php

namespace BlackpigCreatif\ChambreNoir\Services;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ConversionManager
{
    public function __construct(
        protected ImageConverter $converter
    ) {}

    /**
     * Process uploaded file and create conversions
     *
     * @param  TemporaryUploadedFile|string  $file  Temporary uploaded file or path
     * @param  array  $conversions  Conversion configuration
     * @param  string  $disk  Storage disk
     * @param  string  $directory  Target directory
     * @param  string|null  $preset  Optional preset class name for responsive images
     * @return array JSON-serializable array with original, conversions, and preset reference
     */
    public function process(
        TemporaryUploadedFile|string $file,
        array $conversions,
        string $disk,
        string $directory,
        ?string $preset = null
    ): array {
        // Get the real path from TemporaryUploadedFile or use path directly
        $sourcePath = $file instanceof TemporaryUploadedFile
            ? $file->getRealPath()
            : $file;

        // Convert the image
        $result = $this->converter->convert($sourcePath, $conversions, $disk, $directory);

        // Add preset reference if provided
        if ($preset) {
            $result['preset'] = $preset;
        }

        return $result;
    }

    /**
     * Replace existing image with new one
     */
    public function replace(
        TemporaryUploadedFile|string $newFile,
        ?array $oldImageData,
        array $conversions,
        string $disk,
        string $directory,
        ?string $preset = null
    ): array {
        // Delete old image if exists
        if ($oldImageData) {
            $this->converter->delete($oldImageData, $disk);
        }

        // Process new image
        return $this->process($newFile, $conversions, $disk, $directory, $preset);
    }

    /**
     * Delete image and all conversions
     */
    public function delete(?array $imageData, string $disk): void
    {
        if ($imageData) {
            $this->converter->delete($imageData, $disk);
        }
    }

    /**
     * Get URL for a specific conversion
     *
     * @param  array|string|null  $imageData  JSON data or path string
     * @param  string  $conversion  Conversion name (or 'original')
     * @param  string  $disk  Storage disk
     */
    public function getUrl(
        array|string|null $imageData,
        string $conversion = 'original',
        string $disk = 'public'
    ): ?string {
        if (! $imageData) {
            return null;
        }

        // Handle string path (legacy/simple format)
        if (is_string($imageData)) {
            return \Storage::disk($disk)->url($imageData);
        }

        // Handle array format
        if ($conversion === 'original') {
            $path = $imageData['original'] ?? null;
        } else {
            $path = $imageData['conversions'][$conversion] ?? null;
        }

        return $path ? \Storage::disk($disk)->url($path) : null;
    }

    /**
     * Get path for a specific conversion
     */
    public function getPath(
        array|string|null $imageData,
        string $conversion = 'original'
    ): ?string {
        if (! $imageData) {
            return null;
        }

        // Handle string path
        if (is_string($imageData)) {
            return $imageData;
        }

        // Handle array format
        if ($conversion === 'original') {
            return $imageData['original'] ?? null;
        }

        return $imageData['conversions'][$conversion] ?? null;
    }
}
