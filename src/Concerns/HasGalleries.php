<?php

namespace BlackpigCreatif\ChambreNoir\Concerns;

use BlackpigCreatif\ChambreNoir\Models\Gallery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasGalleries
{
    public function galleries(): MorphToMany
    {
        return $this->morphToMany(Gallery::class, 'galleryable', 'galleryables')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function activeGalleries(): MorphToMany
    {
        return $this->galleries()->where('is_active', true);
    }

    public function firstGallery(): ?Gallery
    {
        return $this->activeGalleries()->first();
    }

    public function galleryImages(): Collection
    {
        return $this->galleries()
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->get()
            ->flatMap(fn (Gallery $gallery) => $gallery->images);
    }
}
