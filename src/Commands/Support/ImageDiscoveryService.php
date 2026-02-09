<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use Illuminate\Support\Collection;

/**
 * Service for discovering images that need regeneration
 */
class ImageDiscoveryService
{
    public function __construct(
        protected ModelImageDiscoverer $modelDiscoverer,
        protected BlockImageDiscoverer $blockDiscoverer,
        protected SeoImageDiscoverer $seoDiscoverer,
    ) {}

    /**
     * Discover all images that match the given options
     *
     * @return Collection<RegenerableImage>
     */
    public function discover(RegenerateOptions $options): Collection
    {
        $images = collect();

        // Discover from regular Eloquent models
        if ($options->shouldScanModels()) {
            $modelImages = $this->modelDiscoverer->discover($options);
            $images = $images->merge($modelImages);
        }

        // Discover from Atelier blocks
        if ($options->shouldScanBlocks()) {
            $blockImages = $this->blockDiscoverer->discover($options);
            $images = $images->merge($blockImages);
        }

        // Discover from Sceau SEO data
        if ($options->shouldScanSeo()) {
            $seoImages = $this->seoDiscoverer->discover($options);
            $images = $images->merge($seoImages);
        }

        return $images;
    }

    /**
     * Get count of images by type
     */
    public function getCountsByType(RegenerateOptions $options): array
    {
        $counts = [
            'models' => 0,
            'blocks' => 0,
            'seo' => 0,
            'total' => 0,
        ];

        if ($options->shouldScanModels()) {
            $counts['models'] = $this->modelDiscoverer->count($options);
        }

        if ($options->shouldScanBlocks()) {
            $counts['blocks'] = $this->blockDiscoverer->count($options);
        }

        if ($options->shouldScanSeo()) {
            $counts['seo'] = $this->seoDiscoverer->count($options);
        }

        $counts['total'] = $counts['models'] + $counts['blocks'] + $counts['seo'];

        return $counts;
    }
}
