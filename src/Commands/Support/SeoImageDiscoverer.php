<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Discovers images from Sceau SEO data
 */
class SeoImageDiscoverer
{
    /**
     * Discover images from Sceau SEO data
     *
     * @return Collection<RegenerableImage>
     */
    public function discover(RegenerateOptions $options): Collection
    {
        // Guard: Check if Sceau is installed and table exists
        if (!$this->isSceauAvailable()) {
            return collect();
        }

        $images = collect();

        // Query sceau_seo_data for og_image field
        $query = DB::table('sceau_seo_data')
            ->select(
                'id',
                'seoable_type',
                'seoable_id',
                'og_image'
            )
            ->whereNotNull('og_image');

        // Filter by specific ID if specified
        if ($options->hasIdFilter()) {
            $query->where('id', $options->id);
        }

        // Filter by field (always og_image for SEO)
        if ($options->hasFieldFilter() && $options->field !== 'og_image') {
            // If field filter is specified but not og_image, return empty
            return collect();
        }

        $records = $query->get();

        foreach ($records as $record) {
            // Decode og_image JSON
            $data = json_decode($record->og_image, true);

            // Skip if not a valid array
            if (!is_array($data)) {
                continue;
            }

            // Check if this is ChambreNoir data
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

            // Create RegenerableImage for SEO
            $images->push(RegenerableImage::fromSeo(
                seoId: $record->id,
                seoableType: $record->seoable_type,
                seoableId: $record->seoable_id,
                field: 'og_image',
                data: $data,
                disk: $disk
            ));
        }

        return $images;
    }

    /**
     * Count images from Sceau SEO data
     */
    public function count(RegenerateOptions $options): int
    {
        return $this->discover($options)->count();
    }

    /**
     * Check if Sceau package is installed and table exists
     */
    protected function isSceauAvailable(): bool
    {
        // Check if Sceau package is installed by checking for a known class
        if (!class_exists(\BlackpigCreatif\Sceau\Models\SeoData::class)) {
            return false;
        }

        // Check if required table exists
        if (!Schema::hasTable('sceau_seo_data')) {
            return false;
        }

        return true;
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
