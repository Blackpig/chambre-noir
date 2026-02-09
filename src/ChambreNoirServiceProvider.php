<?php

namespace BlackpigCreatif\ChambreNoir;

use BlackpigCreatif\ChambreNoir\Commands\MakeChambreNoirConversion;
use BlackpigCreatif\ChambreNoir\Commands\RegenerateChambreNoirImages;
use BlackpigCreatif\ChambreNoir\Commands\Support\BlockImageDiscoverer;
use BlackpigCreatif\ChambreNoir\Commands\Support\ImageDiscoveryService;
use BlackpigCreatif\ChambreNoir\Commands\Support\ImageRegenerationService;
use BlackpigCreatif\ChambreNoir\Commands\Support\ModelImageDiscoverer;
use BlackpigCreatif\ChambreNoir\Services\ImageCleanupService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ChambreNoirServiceProvider extends PackageServiceProvider
{
    public static string $name = 'chambre-noir';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([
                MakeChambreNoirConversion::class,
                RegenerateChambreNoirImages::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register regeneration services
        $this->app->singleton(ModelImageDiscoverer::class);
        $this->app->singleton(BlockImageDiscoverer::class);
        $this->app->singleton(ImageDiscoveryService::class);
        $this->app->singleton(ImageRegenerationService::class);

        // Register cleanup service for models and blocks
        $this->app->singleton(ImageCleanupService::class);
    }
}
