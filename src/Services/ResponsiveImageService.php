<?php

namespace BlackpigCreatif\ChambreNoir\Services;

use BlackpigCreatif\ChambreNoir\Conversions\BaseConversion;
use Illuminate\Support\HtmlString;

class ResponsiveImageService
{
    public function __construct(
        protected ConversionManager $conversionManager
    ) {}

    /**
     * Generate srcset attribute string
     *
     * @param  array  $imageData  Image data with conversions
     * @param  string|null  $sizes  Sizes attribute (null = auto-generate)
     * @param  string  $disk  Storage disk
     * @return array{srcset: string, sizes: string|null, src: string}
     */
    public function generateSrcset(array $imageData, ?string $sizes = null, string $disk = 'public'): array
    {
        // Load preset if available
        $preset = $this->loadPreset($imageData);
        $responsiveConfig = $preset?->getResponsiveConfig() ?? $this->getDefaultResponsiveConfig($imageData);
        $metadata = $preset?->getConversionMetadata() ?? $this->extractMetadataFromPaths($imageData);

        // Build srcset entries
        $srcsetParts = [];
        foreach ($responsiveConfig['srcset'] as $conversionName => $enabled) {
            if (! $enabled || ! isset($imageData['conversions'][$conversionName])) {
                continue;
            }

            $url = $this->conversionManager->getUrl($imageData, $conversionName, $disk);
            $width = $metadata[$conversionName]['width'] ?? null;

            if ($url && $width) {
                $srcsetParts[] = "{$url} {$width}w";
            }
        }

        // Determine sizes attribute
        if ($sizes === null && config('chambre-noir.responsive.auto_generate_sizes', true)) {
            $sizes = $this->generateSizesAttribute($metadata, $responsiveConfig);
        }

        // Default src fallback
        $defaultConversion = $responsiveConfig['default'] ?? 'medium';
        $src = $this->conversionManager->getUrl($imageData, $defaultConversion, $disk)
            ?? $this->conversionManager->getUrl($imageData, 'original', $disk);

        return [
            'srcset' => implode(', ', $srcsetParts),
            'sizes' => $sizes,
            'src' => $src,
        ];
    }

    /**
     * Generate picture element HTML
     *
     * @param  array  $imageData  Image data with conversions
     * @param  array  $attributes  HTML attributes for img tag
     * @param  string  $disk  Storage disk
     */
    public function generatePicture(array $imageData, array $attributes = [], string $disk = 'public'): HtmlString
    {
        // Load preset if available
        $preset = $this->loadPreset($imageData);
        $responsiveConfig = $preset?->getResponsiveConfig() ?? $this->getDefaultResponsiveConfig($imageData);

        $sources = [];

        // Build source elements with media queries
        foreach ($responsiveConfig['picture'] as $conversionName => $mediaQuery) {
            if (! isset($imageData['conversions'][$conversionName])) {
                continue;
            }

            $url = $this->conversionManager->getUrl($imageData, $conversionName, $disk);

            if (! $url) {
                continue;
            }

            // Skip if no media query (will be used as default img)
            if ($mediaQuery === null) {
                continue;
            }

            $sources[] = sprintf('<source srcset="%s" media="%s">', e($url), e($mediaQuery));
        }

        // Default img tag
        $defaultConversion = $responsiveConfig['default'] ?? 'medium';
        $src = $this->conversionManager->getUrl($imageData, $defaultConversion, $disk)
            ?? $this->conversionManager->getUrl($imageData, 'original', $disk);

        $imgAttributes = $this->buildAttributesString(array_merge(['src' => $src], $attributes));
        $img = sprintf('<img %s>', $imgAttributes);

        // Build picture element
        $html = '<picture>';
        $html .= implode('', $sources);
        $html .= $img;
        $html .= '</picture>';

        return new HtmlString($html);
    }

    /**
     * Load preset class if available
     */
    protected function loadPreset(array $imageData): ?BaseConversion
    {
        $presetClass = $imageData['preset'] ?? null;

        if (! $presetClass || ! class_exists($presetClass)) {
            return null;
        }

        try {
            $instance = new $presetClass;

            return $instance instanceof BaseConversion ? $instance : null;
        } catch (\Exception $e) {
            \Log::warning('ChambreNoir: Failed to load preset for responsive images', [
                'preset' => $presetClass,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get default responsive config when no preset available
     */
    protected function getDefaultResponsiveConfig(array $imageData): array
    {
        $conversions = array_keys($imageData['conversions'] ?? []);

        $srcsetConfig = [];
        $pictureConfig = [];

        foreach ($conversions as $name) {
            $srcsetConfig[$name] = true;
            $pictureConfig[$name] = null;
        }

        return [
            'default' => config('chambre-noir.responsive.default_conversion', 'medium'),
            'srcset' => $srcsetConfig,
            'picture' => $pictureConfig,
            'sizes' => config('chambre-noir.responsive.default_sizes'),
        ];
    }

    /**
     * Extract metadata from conversion paths when no preset available
     * Attempts to infer width from filename patterns
     */
    protected function extractMetadataFromPaths(array $imageData): array
    {
        $metadata = [];

        foreach ($imageData['conversions'] ?? [] as $name => $path) {
            // We can't reliably extract width from paths, so return null
            // This means srcset won't work without a preset
            $metadata[$name] = [
                'width' => null,
                'height' => null,
                'fit' => 'contain',
            ];
        }

        return $metadata;
    }

    /**
     * Auto-generate sizes attribute from conversion widths
     */
    protected function generateSizesAttribute(array $metadata, array $responsiveConfig): string
    {
        $breakpoints = config('chambre-noir.responsive.breakpoints', [
            'lg' => 1024,
            'md' => 768,
        ]);

        // Sort conversions by width descending
        $sortedConversions = collect($metadata)
            ->filter(fn ($meta) => isset($meta['width']))
            ->sortByDesc(fn ($meta) => $meta['width']);

        if ($sortedConversions->isEmpty()) {
            return '100vw';
        }

        $sizesParts = [];
        $prevWidth = null;

        foreach ($sortedConversions as $name => $meta) {
            $width = $meta['width'];

            // Find appropriate breakpoint
            foreach ($breakpoints as $breakpointName => $breakpointPx) {
                if ($width >= $breakpointPx) {
                    $sizesParts[] = "(min-width: {$breakpointPx}px) {$width}px";
                    break;
                }
            }

            $prevWidth = $width;
        }

        // Add fallback
        $smallestWidth = $sortedConversions->last()['width'] ?? '100vw';
        $sizesParts[] = "{$smallestWidth}px";

        return implode(', ', $sizesParts);
    }

    /**
     * Build HTML attributes string
     */
    protected function buildAttributesString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = e($key);
            } else {
                $parts[] = sprintf('%s="%s"', e($key), e($value));
            }
        }

        return implode(' ', $parts);
    }
}
