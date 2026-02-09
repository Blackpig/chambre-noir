<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Discovers images from Atelier blocks
 */
class BlockImageDiscoverer
{
    /**
     * Discover images from Atelier blocks
     *
     * @return Collection<RegenerableImage>
     */
    public function discover(RegenerateOptions $options): Collection
    {
        // Guard: Check if Atelier is installed and tables exist
        if (!$this->isAtelierAvailable()) {
            return collect();
        }

        $images = collect();

        // Query atelier_block_attributes for ChambreNoir data
        $query = DB::table('atelier_block_attributes')
            ->join('atelier_blocks', 'atelier_blocks.id', '=', 'atelier_block_attributes.block_id')
            ->select(
                'atelier_block_attributes.id as attribute_id',
                'atelier_block_attributes.block_id',
                'atelier_block_attributes.key as field',
                'atelier_block_attributes.value',
                'atelier_blocks.block_type'
            );

        // Filter by block type if specified
        if ($options->hasBlockTypeFilter()) {
            $query->where('atelier_blocks.block_type', $options->blockType);
        }

        // Filter by field if specified
        if ($options->hasFieldFilter()) {
            $query->where('atelier_block_attributes.key', $options->field);
        }

        // Filter by block ID if specified (using ID option)
        if ($options->hasIdFilter()) {
            $query->where('atelier_blocks.id', $options->id);
        }

        $attributes = $query->get();

        foreach ($attributes as $attribute) {
            // Try to decode value as JSON
            $data = json_decode($attribute->value, true);

            // Skip if not a valid array (could be null, int, float, etc.)
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

            // Create RegenerableImage
            $images->push(RegenerableImage::fromBlock(
                blockId: $attribute->block_id,
                field: $attribute->field,
                data: $data,
                disk: $disk
            ));
        }

        return $images;
    }

    /**
     * Count images from Atelier blocks
     */
    public function count(RegenerateOptions $options): int
    {
        return $this->discover($options)->count();
    }

    /**
     * Check if Atelier package is installed and tables exist
     */
    protected function isAtelierAvailable(): bool
    {
        // Check if Atelier package is installed by checking for a known class
        if (!class_exists(\BlackpigCreatif\Atelier\Models\AtelierBlock::class)) {
            return false;
        }

        // Check if required tables exist
        if (!Schema::hasTable('atelier_blocks') || !Schema::hasTable('atelier_block_attributes')) {
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
