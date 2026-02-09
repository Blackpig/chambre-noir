<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Quality
    |--------------------------------------------------------------------------
    |
    | The default quality for image conversions (1-100).
    | Higher values = better quality but larger file sizes.
    |
    */
    'quality' => env('CHAMBRE_NOIR_QUALITY', 90),

    /*
    |--------------------------------------------------------------------------
    | Default Disk
    |--------------------------------------------------------------------------
    |
    | The default storage disk for conversions.
    |
    */
    'disk' => env('CHAMBRE_NOIR_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Conversions Directory
    |--------------------------------------------------------------------------
    |
    | The subdirectory within the upload directory to store conversions.
    |
    */
    'conversions_directory' => 'conversions',

    /*
    |--------------------------------------------------------------------------
    | Conversion Presets
    |--------------------------------------------------------------------------
    |
    | Define reusable conversion presets for common use cases.
    | You can reference these in your forms using ->preset('hero').
    |
    */
    'presets' => [
        'hero' => [
            'thumb' => ['width' => 200, 'height' => 200, 'fit' => 'crop'],
            'medium' => ['width' => 800, 'height' => 600, 'fit' => 'contain'],
            'large' => ['width' => 1920, 'height' => 1080, 'fit' => 'max'],
            'desktop' => ['width' => 1920, 'height' => 1080, 'fit' => 'max'],
            'mobile' => ['width' => 768, 'height' => 1024, 'fit' => 'max'],
        ],

        'gallery' => [
            'thumb' => ['width' => 200, 'height' => 200, 'fit' => 'crop'],
            'medium' => ['width' => 800, 'height' => 600, 'fit' => 'contain'],
            'large' => ['width' => 1600, 'height' => 1200, 'fit' => 'max'],
        ],

        'thumbnail' => [
            'small' => ['width' => 150, 'height' => 150, 'fit' => 'crop'],
            'medium' => ['width' => 300, 'height' => 300, 'fit' => 'crop'],
            'large' => ['width' => 600, 'height' => 600, 'fit' => 'crop'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Responsive Images
    |--------------------------------------------------------------------------
    |
    | Default configuration for responsive image generation.
    | These can be overridden in individual BaseConversion classes.
    |
    */
    'responsive' => [
        // Default conversion to use as fallback <img src="">
        'default_conversion' => 'medium',

        // Default sizes attribute for srcset
        // Set to null to auto-calculate from conversion widths
        'default_sizes' => null,

        // Automatically generate sizes attribute from conversion widths
        'auto_generate_sizes' => true,

        // Default breakpoints for auto-generated media queries (px)
        'breakpoints' => [
            'sm' => 640,
            'md' => 768,
            'lg' => 1024,
            'xl' => 1280,
            '2xl' => 1536,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fit Methods
    |--------------------------------------------------------------------------
    |
    | Available fit methods for image conversions:
    |
    | - crop: Crops and resizes to exact dimensions
    | - contain: Fits within dimensions, maintains aspect ratio
    | - max: Scales down to fit within dimensions (never scales up)
    | - fill: Fills dimensions, may crop to maintain aspect ratio
    |
    */
];
