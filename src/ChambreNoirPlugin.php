<?php

namespace BlackpigCreatif\ChambreNoir;

use BlackpigCreatif\ChambreNoir\Resources\GalleryResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class ChambreNoirPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'chambre-noir';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            GalleryResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
