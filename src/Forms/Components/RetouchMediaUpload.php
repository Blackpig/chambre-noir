<?php

namespace BlackpigCreatif\ChambreNoir\Forms\Components;

use BlackpigCreatif\ChambreNoir\Conversions\BaseConversion;
use BlackpigCreatif\ChambreNoir\Services\ConversionManager;
use BlackpigCreatif\ChambreNoir\StateCasts\RetouchMediaUploadStateCast;
use Closure;
use Filament\Forms\Components\FileUpload;

class RetouchMediaUpload extends FileUpload
{
    protected array|Closure|null $conversions = null;

    protected string|Closure|BaseConversion|null $preset = null;

    protected bool $shouldConvert = true;

    protected bool|Closure $showAttribution = true;

    protected bool|array|Closure|null $attributionRequired = null;

    protected ?array $cachedAttributionData = null;

    /**
     * Override hydrateState to cache attribution data BEFORE StateCast transformation
     * This is the earliest point where we can intercept the ChambreNoir JSON structure
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true): void
    {
        // Get the raw state from Livewire BEFORE any transformations
        $rawState = $this->getRawState();

        // If this is ChambreNoir format, cache attribution data for form population
        if (is_array($rawState) && isset($rawState['original'])) {
            if ($this->shouldShowAttribution() && isset($rawState['attribution'])) {
                $this->cachedAttributionData = $rawState['attribution'];
            }
        }

        // Let parent handle the rest (including StateCast transformation)
        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // RetouchMediaUpload is image-only - enforce at component level
        $this->image();
        $this->acceptedFileTypes(['image/*']);

        // Use custom view with attribution fields
        $this->view('chambre-noir::components.retouch-media-upload');

        // Populate attribution fields (image data is cached in hydrateState)
        $this->afterStateHydrated(function () {
            if ($this->cachedAttributionData) {
                $this->populateAttributionFields($this->cachedAttributionData);
                $this->cachedAttributionData = null;
            }
        });

        // Process conversions when form data is being prepared for saving (dehydration)
        // This runs AFTER FileUpload has done its work but BEFORE data is saved
        $this->dehydrateStateUsing(function ($state) {
            // Get old data from the model record or block (before changes)
            $oldImageData = $this->getOldImageData();

            // Handle deletion: if we had an image but now state is empty/null
            if (empty($state) && $oldImageData) {
                $cleanupService = app(\BlackpigCreatif\ChambreNoir\Services\ImageCleanupService::class);
                $cleanupService->cleanupSingleImage($oldImageData, $this->getDiskName(), [
                    'field' => $this->getName(),
                    'action' => 'field_cleared',
                ]);
                return null;
            }

            if (! $this->shouldConvert) {
                return $state;
            }

            // Skip if state is empty or already processed (array with conversions)
            if (empty($state) || (is_array($state) && isset($state['original']))) {
                return $state;
            }

            // FileUpload returns data in different formats:
            // - String path: "blocks/hero/image.jpg"
            // - UUID array: {"uuid-here": "blocks/hero/image.jpg"}

            // Extract the actual file path from whatever format we receive
            $filePath = $this->extractFilePathFromState($state);

            if (! $filePath) {
                return $state;
            }

            // Handle replacement: if we had an old image and now processing a new one
            if ($oldImageData) {
                $cleanupService = app(\BlackpigCreatif\ChambreNoir\Services\ImageCleanupService::class);
                $cleanupService->cleanupSingleImage($oldImageData, $this->getDiskName(), [
                    'field' => $this->getName(),
                    'action' => 'image_replaced',
                ]);
            }

            // Process this file and create conversions
            $result = $this->processUploadedFilePath($filePath);

            // Merge attribution data if enabled and result is array
            if ($this->shouldShowAttribution() && is_array($result)) {
                $attribution = $this->getAttributionData();
                if ($attribution) {
                    $result['attribution'] = $attribution;
                }
            }

            return $result;
        });
    }

    /**
     * Set image conversions directly
     */
    public function conversions(array|Closure $conversions): static
    {
        $this->conversions = $conversions;

        return $this;
    }

    /**
     * Use a preset for conversions
     * Accepts: string (config key), Closure, BaseConversion::class, or BaseConversion instance
     */
    public function preset(string|Closure|BaseConversion $preset): static
    {
        $this->preset = $preset;

        return $this;
    }

    /**
     * Get conversions (from preset or direct config)
     * Returns normalized conversions with defaults applied
     */
    public function getConversions(): ?array
    {
        $conversions = null;

        // Priority 1: Explicit conversions() call
        if ($this->conversions !== null) {
            $conversions = $this->evaluate($this->conversions);
        }
        // Priority 2: preset() call
        elseif ($this->preset !== null) {
            $conversions = $this->resolvePreset($this->preset);
        }

        // If no conversions resolved, return null (fall back to standard FileUpload)
        if (empty($conversions)) {
            return null;
        }

        // Normalize conversions (add defaults for quality, fit, etc.)
        return $this->normalizeConversions($conversions);
    }

    /**
     * Resolve preset to conversions array
     * Supports: config key string, Closure, BaseConversion class name, BaseConversion instance
     */
    protected function resolvePreset(string|Closure|BaseConversion $preset): ?array
    {
        // Evaluate if it's a Closure
        $evaluated = $this->evaluate($preset);

        // String class name - instantiate and call toArray()
        if (is_string($evaluated) && class_exists($evaluated)) {
            try {
                $instance = app()->make($evaluated);

                if ($instance instanceof BaseConversion) {
                    return $instance->toArray(); // Already normalized by BaseConversion
                }
            } catch (\Exception $e) {
                \Log::warning('ChambreNoir: Failed to instantiate conversion class', [
                    'class' => $evaluated,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        // BaseConversion instance - call toArray()
        if ($evaluated instanceof BaseConversion) {
            return $evaluated->toArray(); // Already normalized by BaseConversion
        }

        // String config key - look up in config
        if (is_string($evaluated)) {
            $conversions = config("chambre-noir.presets.{$evaluated}");

            if (! $conversions) {
                \Log::warning('ChambreNoir: Preset not found in config', [
                    'preset' => $evaluated,
                    'available_presets' => array_keys(config('chambre-noir.presets', [])),
                ]);

                return null; // Fall back to standard FileUpload
            }

            return $conversions;
        }

        // Array result from Closure
        if (is_array($evaluated)) {
            return $evaluated;
        }

        return null;
    }

    /**
     * Normalize conversions by adding defaults
     * Only called for raw arrays (config presets, arrays, closures)
     * BaseConversion instances already return normalized arrays
     */
    protected function normalizeConversions(array $conversions): array
    {
        // If conversions came from BaseConversion, they're already normalized
        // Check if first conversion has quality and fit - if so, assume normalized
        $firstConversion = reset($conversions);
        if (is_array($firstConversion) && isset($firstConversion['quality']) && isset($firstConversion['fit'])) {
            return $conversions; // Already normalized
        }

        // Apply defaults from config or hardcoded values
        $globalQuality = config('chambre-noir.quality', 85);
        $globalFit = 'contain';

        $normalized = [];

        foreach ($conversions as $name => $config) {
            $normalized[$name] = array_merge([
                'quality' => $globalQuality,
                'fit' => $globalFit,
            ], $config); // User config overrides defaults
        }

        return $normalized;
    }

    /**
     * Disable automatic conversion (use FileUpload normally)
     */
    public function withoutConversions(): static
    {
        $this->shouldConvert = false;

        return $this;
    }

    /**
     * Enable or disable attribution fields
     */
    public function attribution(bool|Closure $condition = true): static
    {
        $this->showAttribution = $condition;

        return $this;
    }

    /**
     * Configure which attribution fields are required
     *
     * @param bool|array|Closure $condition
     *   - bool: All fields required (true) or optional (false)
     *   - array: ['name' => bool, 'link' => bool] for individual control
     *   - Closure: Dynamic logic returning bool or array
     */
    public function attributionRequired(bool|array|Closure $condition = true): static
    {
        $this->attributionRequired = $condition;

        return $this;
    }

    /**
     * Check if attribution fields should be shown
     */
    public function shouldShowAttribution(): bool
    {
        return (bool) $this->evaluate($this->showAttribution);
    }

    /**
     * Check if attribution name field is required
     */
    public function isAttributionNameRequired(): bool
    {
        if (! $this->shouldShowAttribution()) {
            return false;
        }

        // If attributionRequired is null, default to the component's required state
        if ($this->attributionRequired === null) {
            return $this->isRequired();
        }

        $evaluated = $this->evaluate($this->attributionRequired);

        // If bool, apply to all fields
        if (is_bool($evaluated)) {
            return $evaluated;
        }

        // If array, check specific field, fall back to component's required state
        if (is_array($evaluated)) {
            return $evaluated['name'] ?? $this->isRequired();
        }

        return false;
    }

    /**
     * Check if attribution link field is required
     */
    public function isAttributionLinkRequired(): bool
    {
        if (! $this->shouldShowAttribution()) {
            return false;
        }

        // If attributionRequired is null, default to the component's required state
        if ($this->attributionRequired === null) {
            return $this->isRequired();
        }

        $evaluated = $this->evaluate($this->attributionRequired);

        // If bool, apply to all fields
        if (is_bool($evaluated)) {
            return $evaluated;
        }

        // If array, check specific field, fall back to component's required state
        if (is_array($evaluated)) {
            return $evaluated['link'] ?? $this->isRequired();
        }

        return false;
    }

    /**
     * Get attribution data from Livewire component
     */
    protected function getAttributionData(): ?array
    {
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        $name = data_get($livewire, "{$statePath}_attribution_name");
        $link = data_get($livewire, "{$statePath}_attribution_link");

        // Only return if at least one value is present
        if ($name || $link) {
            return [
                'name' => $name,
                'link' => $link,
            ];
        }

        return null;
    }

    /**
     * Populate attribution fields in Livewire component
     */
    protected function populateAttributionFields(array $attribution): void
    {
        $livewire = $this->getLivewire();
        $statePath = $this->getStatePath();

        data_set($livewire, "{$statePath}_attribution_name", $attribution['name'] ?? null);
        data_set($livewire, "{$statePath}_attribution_link", $attribution['link'] ?? null);
    }

    /**
     * Get preset reference for storage with image data
     * Returns class name if preset is a BaseConversion, null otherwise
     */
    protected function getPresetReference(): ?string
    {
        if ($this->preset === null) {
            return null;
        }

        $evaluated = $this->evaluate($this->preset);

        // String class name that extends BaseConversion
        if (is_string($evaluated) && class_exists($evaluated)) {
            if (is_subclass_of($evaluated, BaseConversion::class)) {
                return $evaluated;
            }
        }

        // BaseConversion instance
        if ($evaluated instanceof BaseConversion) {
            return get_class($evaluated);
        }

        // Config key, Closure, or array - no preset class reference
        return null;
    }

    /**
     * Extract file path from FileUpload state
     * Handles both string and UUID-keyed array formats
     */
    protected function extractFilePathFromState($state): ?string
    {
        // Simple string path
        if (is_string($state)) {
            return $state;
        }

        // UUID-keyed array format from FileUpload: {"uuid": "path"}
        if (is_array($state)) {
            foreach ($state as $key => $value) {
                // Check for UUID key with string path value
                if (is_string($key) && is_string($value) &&
                    preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
                    return $value;
                }

                // Also handle simple numeric array [0 => "path"]
                if (is_numeric($key) && is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Process uploaded file path and create conversions
     * Called during dehydration after FileUpload has saved the file
     */
    protected function processUploadedFilePath(string $filePath): array|string
    {
        $conversions = $this->getConversions();

        if (empty($conversions)) {
            return $filePath; // No conversions, return path as-is
        }

        $disk = \Storage::disk($this->getDiskName());

        // Check if file exists on disk
        if (! $disk->exists($filePath)) {
            return $filePath;
        }

        try {
            $manager = app(ConversionManager::class);

            // Get full path to the file
            $fullPath = $disk->path($filePath);

            // Extract directory from the stored path
            $pathInfo = pathinfo($filePath);
            $directory = $pathInfo['dirname'] ?? '';

            // Get preset reference (class name or null)
            $presetReference = $this->getPresetReference();

            // Process conversions using the saved file and return JSON structure
            return $manager->process(
                file: $fullPath,
                conversions: $conversions,
                disk: $this->getDiskName(),
                directory: $directory,
                preset: $presetReference
            );
        } catch (\Exception $e) {
            \Log::error('ChambreNoir: Conversion processing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Graceful fallback: return original path
            return $filePath;
        }
    }

    /**
     * Get old image data before changes
     * Handles both regular models and Atelier blocks
     */
    protected function getOldImageData(): ?array
    {
        $fieldName = $this->getName();
        $record = $this->getRecord();

        if (!$record || !$fieldName) {
            return null;
        }

        // FOR MODELS: Use getOriginal() to get unchanged data
        if (method_exists($record, 'getOriginal')) {
            $oldValue = $record->getOriginal($fieldName);

            if ($oldValue !== null) {
                return $this->parseImageData($oldValue);
            }
        }

        // FOR BLOCKS: Block images are saved through Atelier's BlockManager, which bypasses
        // this component's dehydrateStateUsing lifecycle. Block cleanup is handled by
        // BlockImageCleanupService which is called from Atelier's BlockManager.saveBlockAttributes()
        return null;
    }

    /**
     * Parse image data from various formats
     */
    protected function parseImageData($value): ?array
    {
        // If it's a JSON string, decode it
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded['original'])) {
                return $decoded;
            }
        }

        // If it's already an array with ChambreNoir structure
        if (is_array($value) && isset($value['original'])) {
            return $value;
        }

        return null;
    }

    /**
     * Override default StateCasts to use our custom RetouchMediaUploadStateCast
     * This ensures ChambreNoir JSON structures are properly transformed during hydration
     */
    public function getDefaultStateCasts(): array
    {
        // Get parent casts but filter out the FileUploadStateCast
        $parentCasts = parent::getDefaultStateCasts();

        // Filter out Filament's FileUploadStateCast and add ours
        $casts = array_filter($parentCasts, function ($cast) {
            return ! ($cast instanceof \Filament\Schemas\Components\StateCasts\FileUploadStateCast);
        });

        // Add our custom StateCast that handles ChambreNoir format
        $casts[] = app(RetouchMediaUploadStateCast::class, ['isMultiple' => $this->isMultiple()]);

        return $casts;
    }
}
