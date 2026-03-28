<?php

namespace BlackpigCreatif\ChambreNoir\Models;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Gallery extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'conversion',
        'panel',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Gallery $gallery): void {
            $gallery->slug ??= Str::slug($gallery->title);

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
