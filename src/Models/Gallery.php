<?php

namespace BlackpigCreatif\ChambreNoir\Models;

use BlackpigCreatif\ChambreNoir\Jobs\CleanupGalleryImages;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

class Gallery extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'conversion',
        'panel',
        'is_active',
    ];

    /** @var array<int, string> */
    public array $translatable = ['title'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (Gallery $gallery): void {
            // DB cascade will wipe gallery_images rows without firing Eloquent observers.
            // Capture image data NOW (before rows are gone) and dispatch a queued job
            // so the physical files are cleaned up after the delete completes.
            $imagesData = $gallery->images
                ->map(fn (GalleryImage $image): ?array => $image->image)
                ->filter(fn (?array $data): bool => is_array($data) && isset($data['original']))
                ->values()
                ->all();

            if (! empty($imagesData)) {
                CleanupGalleryImages::dispatch(
                    $imagesData,
                    config('chambre-noir.disk', 'public'),
                    $gallery->id,
                );
            }
        });

        static::creating(function (Gallery $gallery): void {
            $gallery->slug ??= Str::slug((string) ($gallery->getTranslation('title', 'en', false) ?: $gallery->getTranslation('title', 'fr', false)));

            try {
                $gallery->panel ??= Filament::getCurrentPanel()?->getId();
            } catch (\Throwable) {
                // Panel not available in non-HTTP contexts (seeding, testing, etc.)
            }
        });
    }

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->orderBy('sort_order');
    }
}
