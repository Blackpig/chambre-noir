<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use BlackpigCreatif\ChambreNoir\Services\ImageConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Service for regenerating image conversions
 */
class ImageRegenerationService
{
    public function __construct(
        protected ImageConverter $converter,
    ) {}

    /**
     * Regenerate an image
     *
     * @return array{success: bool, message: string, error: ?string}
     */
    public function regenerate(
        RegenerableImage $image,
        RegenerateOptions $options
    ): array {
        try {
            // Validate image has required data
            if (!$image->hasOriginal()) {
                return [
                    'success' => false,
                    'message' => 'No original image found',
                    'error' => 'missing_original',
                ];
            }

            if (!$image->hasPreset()) {
                return [
                    'success' => false,
                    'message' => 'No preset class found',
                    'error' => 'missing_preset',
                ];
            }

            // Check if original file exists on disk
            $originalPath = $image->getOriginalPath();
            if (!Storage::disk($image->disk)->exists($originalPath)) {
                return [
                    'success' => false,
                    'message' => "Original file not found: {$originalPath}",
                    'error' => 'file_not_found',
                ];
            }

            // Get preset instance (resolve through container to respect bindings)
            $presetClass = $image->getPreset();

            if (!class_exists($presetClass)) {
                return [
                    'success' => false,
                    'message' => "Preset class not found: {$presetClass}",
                    'error' => 'preset_not_found',
                ];
            }

            // Use app() to resolve, which respects service provider bindings
            $preset = app($presetClass);

            // Store old conversions for backup
            $oldConversions = $image->getConversions();

            // Backup old conversions if requested
            if ($options->backup) {
                $this->backupConversions($image);
            }

            // Delete old conversions BEFORE regenerating
            // (otherwise new files with same names get deleted)
            // TODO: This is not failure-safe. If regeneration fails after this point,
            // old conversions are gone and new ones don't exist. Need to implement
            // atomic rollback: generate new conversions first, only delete old on success.
            // See: .claude/post-launch-todos.md - "Improve Regeneration Failure Handling"
            $this->deleteOldConversions($image, $oldConversions);

            // Get conversions array from preset
            $conversions = $preset->toArray();

            // Generate new conversions (using regenerate method which doesn't move the original)
            $result = $this->converter->regenerate(
                originalPath: $originalPath,
                conversions: $conversions,
                disk: $image->disk
            );

            // Update database with new conversion data
            $newData = array_merge($image->data, [
                'original' => $result['original'],
                'conversions' => $result['conversions'],
            ]);

            $this->updateDatabase($image, $newData);

            return [
                'success' => true,
                'message' => 'Regenerated successfully',
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'exception',
            ];
        }
    }

    /**
     * Backup existing conversions
     */
    protected function backupConversions(RegenerableImage $image): void
    {
        $conversions = $image->getConversions();
        $disk = Storage::disk($image->disk);

        foreach ($conversions as $name => $path) {
            if (!$disk->exists($path)) {
                continue;
            }

            $backupPath = $this->getBackupPath($path);
            $disk->copy($path, $backupPath);
        }
    }

    /**
     * Delete old conversion files
     */
    protected function deleteOldConversions(RegenerableImage $image, array $oldConversions): void
    {
        $disk = Storage::disk($image->disk);

        foreach ($oldConversions as $name => $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Update database with new image data
     */
    protected function updateDatabase(RegenerableImage $image, array $newData): void
    {
        $jsonData = json_encode($newData);

        if ($image->isModel()) {
            // Update model record
            DB::table($this->getTableName($image->model))
                ->where('id', $image->modelId)
                ->update([
                    $image->field => $jsonData,
                ]);
        } elseif ($image->isSeo()) {
            // Update SEO data
            DB::table('sceau_seo_data')
                ->where('id', $image->seoId)
                ->update([
                    $image->field => $jsonData,
                ]);
        } else {
            // Update block attribute
            DB::table('atelier_block_attributes')
                ->where('block_id', $image->blockId)
                ->where('key', $image->field)
                ->update([
                    'value' => $jsonData,
                ]);
        }
    }

    /**
     * Get backup path for a file
     */
    protected function getBackupPath(string $path): string
    {
        $pathInfo = pathinfo($path);
        $timestamp = now()->format('YmdHis');

        return sprintf(
            '%s/%s_backup_%s.%s',
            $pathInfo['dirname'],
            $pathInfo['filename'],
            $timestamp,
            $pathInfo['extension']
        );
    }

    /**
     * Get database table name from model class
     */
    protected function getTableName(string $modelClass): string
    {
        $model = new $modelClass();
        return $model->getTable();
    }
}
