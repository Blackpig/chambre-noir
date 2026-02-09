<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Discovers images from regular Eloquent models
 */
class ModelImageDiscoverer
{
    /**
     * Discover images from Eloquent models
     *
     * @return Collection<RegenerableImage>
     */
    public function discover(RegenerateOptions $options): Collection
    {
        $images = collect();

        // Get all models to scan
        $models = $this->getModelsToScan($options);

        foreach ($models as $modelClass) {
            $modelImages = $this->discoverFromModel($modelClass, $options);
            $images = $images->merge($modelImages);
        }

        return $images;
    }

    /**
     * Count images from Eloquent models
     */
    public function count(RegenerateOptions $options): int
    {
        return $this->discover($options)->count();
    }

    /**
     * Discover images from a specific model class
     *
     * @return Collection<RegenerableImage>
     */
    protected function discoverFromModel(string $modelClass, RegenerateOptions $options): Collection
    {
        $images = collect();

        // Query the model
        $query = $modelClass::query();

        // Filter by ID if specified
        if ($options->hasIdFilter()) {
            $query->where('id', $options->id);
        }

        // Get all records
        $records = $query->get();

        foreach ($records as $record) {
            $recordImages = $this->discoverFromRecord($record, $options);
            $images = $images->merge($recordImages);
        }

        return $images;
    }

    /**
     * Discover images from a model record
     *
     * @return Collection<RegenerableImage>
     */
    protected function discoverFromRecord(Model $record, RegenerateOptions $options): Collection
    {
        $images = collect();

        // Get all attributes that might be ChambreNoir fields
        $attributes = $record->getAttributes();

        foreach ($attributes as $field => $value) {
            // Skip if field filter doesn't match
            if ($options->hasFieldFilter() && $field !== $options->field) {
                continue;
            }

            // Try to decode as JSON
            if (is_string($value)) {
                $data = json_decode($value, true);
                // Skip if not a valid array (could be null, float, int, etc.)
                if (!is_array($data)) {
                    continue;
                }
            } elseif (is_array($value)) {
                $data = $value;
            } else {
                continue;
            }

            // Check if this looks like ChambreNoir data
            if (!$this->isChambreNoirData($data)) {
                continue;
            }

            // Apply conversion filter
            if ($options->hasConversionFilter()) {
                $preset = $data['preset'] ?? null;
                if (!$preset || !Str::contains($preset, $options->conversion)) {
                    continue;
                }
            }

            // Apply disk filter
            $disk = $options->disk ?? 'public';
            if ($options->hasDiskFilter() && $disk !== $options->disk) {
                continue;
            }

            // Create RegenerableImage
            $images->push(RegenerableImage::fromModel(
                modelClass: get_class($record),
                modelId: $record->id,
                field: $field,
                data: $data,
                disk: $disk
            ));
        }

        return $images;
    }

    /**
     * Get list of model classes to scan
     *
     * @return array<string>
     */
    protected function getModelsToScan(RegenerateOptions $options): array
    {
        // If specific model provided, use that
        if ($options->hasModelFilter()) {
            $modelClass = $this->resolveModelClass($options->model);
            return $modelClass ? [$modelClass] : [];
        }

        // Otherwise, scan all models
        return $this->getAllModelsWithTrait();
    }

    /**
     * Get all models that use HasRetouchMedia trait
     *
     * @return array<string>
     */
    protected function getAllModelsWithTrait(): array
    {
        $models = [];
        $modelPath = app_path('Models');

        if (!File::isDirectory($modelPath)) {
            return [];
        }

        $files = File::allFiles($modelPath);

        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file->getPathname());

            if (!$className) {
                continue;
            }

            if ($this->usesHasRetouchMedia($className)) {
                $models[] = $className;
            }
        }

        return $models;
    }

    /**
     * Get class name from file path
     */
    protected function getClassNameFromFile(string $filePath): ?string
    {
        $relativePath = str_replace(app_path() . '/', '', $filePath);
        $relativePath = str_replace('.php', '', $relativePath);
        $className = 'App\\' . str_replace('/', '\\', $relativePath);

        if (!class_exists($className)) {
            return null;
        }

        return $className;
    }

    /**
     * Check if class uses HasRetouchMedia trait
     */
    protected function usesHasRetouchMedia(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);

        // Check if it's a model
        if (!$reflection->isSubclassOf(Model::class)) {
            return false;
        }

        // Check for trait
        $traits = class_uses_recursive($className);

        return in_array(HasRetouchMedia::class, $traits);
    }

    /**
     * Resolve model class name from string
     */
    protected function resolveModelClass(string $model): ?string
    {
        // Try as-is
        if (class_exists($model)) {
            return $model;
        }

        // Try with App\Models namespace
        $withNamespace = "App\\Models\\{$model}";
        if (class_exists($withNamespace)) {
            return $withNamespace;
        }

        return null;
    }

    /**
     * Check if data array is ChambreNoir data
     */
    protected function isChambreNoirData(?array $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        // ChambreNoir data should have 'original' or 'preset' keys
        return isset($data['original']) || isset($data['preset']);
    }
}
