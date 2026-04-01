<?php

namespace BlackpigCreatif\ChambreNoir\Models;

use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;
use BlackpigCreatif\ChambreNoir\Services\ImageCleanupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryImage extends Model
{
    use HasRetouchMedia;

    protected $fillable = [
        'gallery_id',
        'image',
        'caption',
        'sort_order',
    ];

    protected $casts = [
        'image' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (GalleryImage $galleryImage): void {
            if (! $galleryImage->isDirty('image')) {
                return;
            }

            $oldImage = $galleryImage->getOriginal('image');

            if (! is_array($oldImage) || ! isset($oldImage['original'])) {
                return;
            }

            // Only clean up when the actual file was replaced, not just metadata (e.g. attribution).
            // dehydrateStateUsing returns the same 'original' path when only attribution changes.
            $newImage = $galleryImage->image;
            $newOriginal = is_array($newImage) ? ($newImage['original'] ?? null) : null;

            if ($newOriginal === $oldImage['original']) {
                return;
            }

            app(ImageCleanupService::class)->cleanupSingleImage(
                $oldImage,
                config('chambre-noir.disk', 'public'),
                [
                    'model' => self::class,
                    'id' => $galleryImage->getKey(),
                    'action' => 'image_replaced',
                ]
            );
        });

        static::deleting(function (GalleryImage $galleryImage): void {
            $image = $galleryImage->image;

            if (! is_array($image) || ! isset($image['original'])) {
                return;
            }

            app(ImageCleanupService::class)->cleanupSingleImage(
                $image,
                config('chambre-noir.disk', 'public'),
                [
                    'model' => self::class,
                    'id' => $galleryImage->getKey(),
                    'action' => 'record_deleted',
                ]
            );
        });
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
