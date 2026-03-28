<?php

namespace BlackpigCreatif\ChambreNoir\Models;

use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;
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

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
