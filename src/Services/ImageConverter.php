<?php

namespace BlackpigCreatif\ChambreNoir\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ImageConverter
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new ImagickDriver);
    }

    /**
     * Convert an image to multiple sizes
     *
     * @param  string  $sourcePath  Full path to source image
     * @param  array  $conversions  ['thumb' => ['width' => 200, 'height' => 200, 'fit' => 'crop']]
     * @param  string  $disk  Storage disk
     * @param  string  $directory  Base directory (e.g., 'blocks/hero')
     * @return array ['original' => 'path', 'conversions' => ['thumb' => 'path']]
     */
    public function convert(
        string $sourcePath,
        array $conversions,
        string $disk,
        string $directory
    ): array {
        $storage = Storage::disk($disk);

        // Read the source image
        $image = $this->manager->read($sourcePath);

        // Get filename and extension
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        // Store original in target directory
        $originalPath = $directory.'/'.$filename.'.'.$extension;
        $storage->put($originalPath, file_get_contents($sourcePath));

        $result = [
            'original' => $originalPath,
            'conversions' => [],
        ];

        // Create conversions directory
        $conversionsDir = $directory.'/'.config('chambre-noir.conversions_directory', 'conversions');

        foreach ($conversions as $conversionName => $config) {
            $conversionPath = $conversionsDir.'/'.$filename.'-'.$conversionName.'.'.$extension;

            // Clone the image for each conversion
            $converted = clone $image;

            // Apply conversion based on fit method
            $converted = $this->applyConversion($converted, $config);

            // Encode with quality
            $quality = $config['quality'] ?? config('chambre-noir.quality', 90);
            $encoded = $converted->encodeByExtension($extension, quality: $quality);

            // Store conversion
            $storage->put($conversionPath, (string) $encoded);

            $result['conversions'][$conversionName] = $conversionPath;
        }

        return $result;
    }

    /**
     * Apply conversion transformation to image
     */
    protected function applyConversion($image, array $config)
    {
        $width = $config['width'] ?? null;
        $height = $config['height'] ?? null;
        $fit = $config['fit'] ?? 'contain';

        return match ($fit) {
            'crop' => $image->cover($width, $height),
            'contain' => $image->contain($width, $height),
            'max' => $image->scale($width, $height),
            'fill' => $image->resize($width, $height),
            default => $image->contain($width, $height),
        };
    }

    /**
     * Regenerate conversions for an existing image
     * Does NOT move or copy the original - only recreates conversions
     *
     * @param  string  $originalPath  Relative path to original image (e.g., 'faqs/image.jpg')
     * @param  array  $conversions  ['thumb' => ['width' => 200, 'height' => 200, 'fit' => 'crop']]
     * @param  string  $disk  Storage disk
     * @return array ['original' => 'path', 'conversions' => ['thumb' => 'path']]
     */
    public function regenerate(
        string $originalPath,
        array $conversions,
        string $disk
    ): array {
        $storage = Storage::disk($disk);

        // Get absolute path to read the image
        $absolutePath = $storage->path($originalPath);

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("Original image not found: {$absolutePath}");
        }

        // Read the source image
        $image = $this->manager->read($absolutePath);

        // Get filename and extension from the original path
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        $extension = pathinfo($originalPath, PATHINFO_EXTENSION);
        $directory = dirname($originalPath);

        $result = [
            'original' => $originalPath, // Keep existing original path
            'conversions' => [],
        ];

        // Create conversions directory
        $conversionsDir = $directory.'/'.config('chambre-noir.conversions_directory', 'conversions');

        foreach ($conversions as $conversionName => $config) {
            $conversionPath = $conversionsDir.'/'.$filename.'-'.$conversionName.'.'.$extension;

            // Clone the image for each conversion
            $converted = clone $image;

            // Apply conversion based on fit method
            $converted = $this->applyConversion($converted, $config);

            // Encode with quality
            $quality = $config['quality'] ?? config('chambre-noir.quality', 90);
            $encoded = $converted->encodeByExtension($extension, quality: $quality);

            // Store conversion
            $storage->put($conversionPath, (string) $encoded);

            $result['conversions'][$conversionName] = $conversionPath;
        }

        return $result;
    }

    /**
     * Delete an image and all its conversions
     */
    public function delete(array $imageData, string $disk): void
    {
        $storage = Storage::disk($disk);

        // Delete original
        if (isset($imageData['original']) && $storage->exists($imageData['original'])) {
            $storage->delete($imageData['original']);
        }

        // Delete all conversions
        if (isset($imageData['conversions'])) {
            foreach ($imageData['conversions'] as $conversionPath) {
                if ($storage->exists($conversionPath)) {
                    $storage->delete($conversionPath);
                }
            }
        }
    }
}
