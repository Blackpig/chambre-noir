<?php

namespace BlackpigCreatif\ChambreNoir\Conversions;

abstract class BaseConversion
{
    protected int $defaultQuality = 85;

    protected string $defaultFit = 'contain';

    /**
     * Define conversions (without defaults)
     * Child classes implement this to return raw conversion configs
     */
    abstract protected function define(): array;

    /**
     * Include other conversion classes
     * Child classes can override to compose multiple conversion sets
     *
     * @return array<class-string<BaseConversion>>
     */
    protected function includes(): array
    {
        return [];
    }

    /**
     * Get normalized conversions with defaults applied
     * This is the public API - always returns conversions with defaults filled in
     * Automatically merges conversions from included classes
     */
    final public function toArray(): array
    {
        $conversions = [];

        // First, merge conversions from included classes
        foreach ($this->includes() as $conversionClass) {
            $instance = new $conversionClass;
            $conversions = array_merge($conversions, $instance->toArray());
        }

        // Then merge our own conversions (can override included ones)
        $conversions = array_merge($conversions, $this->normalize($this->define()));

        return $conversions;
    }

    /**
     * Normalize conversions by adding defaults
     * Ensures every conversion has quality and fit set
     */
    protected function normalize(array $conversions): array
    {
        $normalized = [];

        foreach ($conversions as $name => $config) {
            $normalized[$name] = array_merge([
                'quality' => $this->defaultQuality,
                'fit' => $this->defaultFit,
            ], $config); // User config overrides defaults
        }

        return $normalized;
    }

    /**
     * Set default quality for all conversions
     * Fluent interface for customization
     */
    public function withQuality(int $quality): static
    {
        $this->defaultQuality = $quality;

        return $this;
    }

    /**
     * Set default fit method for all conversions
     * Fluent interface for customization
     */
    public function withFit(string $fit): static
    {
        $this->defaultFit = $fit;

        return $this;
    }

    /**
     * Get default quality value
     */
    public function getDefaultQuality(): int
    {
        return $this->defaultQuality;
    }

    /**
     * Get default fit method
     */
    public function getDefaultFit(): string
    {
        return $this->defaultFit;
    }

    /**
     * Get responsive image configuration
     * Child classes can override to customize responsive behavior
     *
     * @return array{default: string, srcset: array<string, bool>, picture: array<string, string|null>, sizes: string|null}
     */
    public function getResponsiveConfig(): array
    {
        $conversions = $this->toArray();
        $conversionNames = array_keys($conversions);

        // Build default srcset config (all conversions enabled)
        $srcsetConfig = [];
        foreach ($conversionNames as $name) {
            $srcsetConfig[$name] = true;
        }

        // Build default picture config (all conversions, no media queries)
        $pictureConfig = [];
        foreach ($conversionNames as $name) {
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
     * Get conversion metadata (width, height, fit) for responsive image generation
     *
     * @return array<string, array{width: int|null, height: int|null, fit: string}>
     */
    public function getConversionMetadata(): array
    {
        $conversions = $this->toArray();
        $metadata = [];

        foreach ($conversions as $name => $config) {
            $metadata[$name] = [
                'width' => $config['width'] ?? null,
                'height' => $config['height'] ?? null,
                'fit' => $config['fit'] ?? $this->defaultFit,
            ];
        }

        return $metadata;
    }
}
