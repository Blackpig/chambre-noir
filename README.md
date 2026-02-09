# ChambreNoir

Automatic image conversion, responsive images, and attribution for FilamentPHP v4.

ChambreNoir extends Filament's `FileUpload` with automatic image conversions on upload, responsive image rendering via `<picture>` and `srcset`, and built-in photographer attribution. No database tables, no model relationships -- just JSON stored in any column.

---

## Requirements

- PHP 8.2+
- Laravel 11+ / 12+
- FilamentPHP 4+ / 5+
- Imagick PHP extension
- Intervention Image 3.x

## Installation

```bash
composer require blackpig-creatif/chambre-noir
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=chambre-noir-config
```

---

## Quick Start

### 1. Create a Conversion Preset

```bash
php artisan chambre-noir:make-conversion Hero
```

This generates `app/BlackpigCreatif/ChambreNoir/Conversions/HeroConversion.php`:

```php
<?php

namespace App\BlackpigCreatif\ChambreNoir\Conversions;

use BlackpigCreatif\ChambreNoir\Conversions\BaseConversion;

class HeroConversion extends BaseConversion
{
    protected int $defaultQuality = 85;

    protected function define(): array
    {
        return [
            'small'  => ['width' => 600,  'height' => 600,  'fit' => 'max'],
            'medium' => ['width' => 1200, 'height' => 1200, 'fit' => 'max'],
            'large'  => ['width' => 2400, 'height' => 2400, 'fit' => 'max'],
        ];
    }
}
```

### 2. Use in a Filament Form

```php
use BlackpigCreatif\ChambreNoir\Forms\Components\RetouchMediaUpload;
use App\BlackpigCreatif\ChambreNoir\Conversions\HeroConversion;

RetouchMediaUpload::make('hero_image')
    ->preset(HeroConversion::class)
    ->disk('public')
    ->directory('pages/hero')
    ->required();
```

### 3. Add the Trait to Your Model

```php
use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;

class Page extends Model
{
    use HasRetouchMedia;

    protected $casts = [
        'hero_image' => 'array',
    ];
}
```

### 4. Render in Blade

```blade
{!! $page->getPicture('hero_image', ['alt' => $page->title, 'class' => 'w-full h-auto']) !!}
```

---

## How It Works

When an image is uploaded through `RetouchMediaUpload`:

1. Filament's `FileUpload` saves the original to disk
2. ChambreNoir creates conversions based on the preset
3. A JSON structure is stored in the database column

```json
{
  "original": "pages/hero/image.jpg",
  "conversions": {
    "small": "pages/hero/conversions/image-small.jpg",
    "medium": "pages/hero/conversions/image-medium.jpg",
    "large": "pages/hero/conversions/image-large.jpg"
  },
  "attribution": {
    "name": "Jane Smith",
    "link": "https://janesmith.photography"
  },
  "preset": "App\\BlackpigCreatif\\ChambreNoir\\Conversions\\HeroConversion"
}
```

Old images are automatically cleaned up when replaced or removed.

---

## RetouchMediaUpload

Drop-in replacement for Filament's `FileUpload`. Image-only by default.

### Conversions via Preset (Recommended)

```php
RetouchMediaUpload::make('image')
    ->preset(HeroConversion::class)
```

### Inline Conversions

```php
RetouchMediaUpload::make('image')
    ->conversions([
        'thumb'  => ['width' => 200, 'height' => 200, 'fit' => 'crop'],
        'medium' => ['width' => 800, 'height' => 600, 'fit' => 'contain'],
        'large'  => ['width' => 1920, 'height' => 1080, 'fit' => 'max'],
    ])
```

### Config-based Presets (Legacy)

String keys reference `config/chambre-noir.php` presets. BaseConversion classes are preferred for new projects.

```php
RetouchMediaUpload::make('image')
    ->preset('hero')
```

### Disable Conversions

```php
RetouchMediaUpload::make('document')
    ->withoutConversions()
```

All standard `FileUpload` methods (`disk()`, `directory()`, `imageEditor()`, `maxSize()`, etc.) work as expected.

---

## Conversion Presets

Presets define the image sizes generated on upload. Extend `BaseConversion` and implement `define()`:

```php
use BlackpigCreatif\ChambreNoir\Conversions\BaseConversion;

class ProductConversion extends BaseConversion
{
    protected int $defaultQuality = 90;
    protected string $defaultFit = 'contain';

    protected function define(): array
    {
        return [
            'thumb'  => ['width' => 300, 'height' => 300, 'fit' => 'crop'],
            'medium' => ['width' => 800, 'height' => 800],
            'large'  => ['width' => 1600, 'height' => 1600, 'quality' => 92],
        ];
    }
}
```

Each conversion accepts `width`, `height`, `fit`, and `quality`. Missing values inherit from class defaults.

### Fit Methods

| Fit | Behaviour | Use case |
|-----|-----------|----------|
| `crop` | Exact dimensions, crops overflow | Thumbnails, avatars |
| `contain` | Fits within bounds, preserves ratio | General images |
| `max` | Scales down only, never up | Responsive images |
| `fill` | Fills dimensions, may distort | Background fills |

### Composition

Presets can include other presets via `includes()`:

```php
class FullConversion extends BaseConversion
{
    protected function includes(): array
    {
        return [SocialImageConversion::class];
    }

    protected function define(): array
    {
        return [
            'thumb'  => ['width' => 200, 'height' => 200, 'fit' => 'crop'],
            'large'  => ['width' => 1920, 'height' => 1080, 'fit' => 'max'],
        ];
    }
}
```

Included conversions are merged first; the defining class can override them.

### Responsive Configuration

Override `getResponsiveConfig()` to control `<picture>` and `srcset` behaviour:

```php
public function getResponsiveConfig(): array
{
    return [
        'default' => 'medium',
        'srcset' => [
            'small'  => true,
            'medium' => true,
            'large'  => true,
        ],
        'picture' => [
            'large'  => '(min-width: 1024px)',
            'medium' => '(min-width: 768px)',
            'small'  => null, // fallback
        ],
        'sizes' => '(min-width: 768px) 50vw, 100vw',
    ];
}
```

See [Conversion Presets](docs/conversion-presets.md) for the full reference.

### Built-in Preset

`SocialImageConversion` ships with ChambreNoir, producing `og` (1200x630) and `twitter` (1200x600) crops at 90% quality.

---

## HasRetouchMedia Trait

Add to any Eloquent model (or Atelier block) to access rendering helpers. Ensure the field is cast to `array`.

### Rendering Methods

```php
// <picture> with responsive sources (recommended)
$model->getPicture('image', ['alt' => '...', 'class' => '...'])

// <img> with srcset attribute
$model->getImageWithSrcset('image', $sizes, ['alt' => '...'])

// <figure> with optional <figcaption> (uses attribution if present)
$model->getFigure('image', [
    'mode'    => 'picture',           // 'picture', 'srcset', or 'image'
    'image'   => ['alt' => '...', 'class' => '...'],
    'figure'  => ['class' => 'relative'],
    'caption' => ['class' => 'text-sm text-gray-600'],
])

// Simple <img> for a specific conversion
$model->getImage('image', 'thumb', ['alt' => '...'])
```

### URL Access

```php
$model->getMediaUrl('image', 'large')           // URL string
$model->getMediaUrl('image', 'original')         // Original file URL
$model->getMediaUrls('gallery', 'medium')        // Array of URLs (multiple files)
$model->getMediaPath('image', 'large')           // Storage path (not URL)
```

### Srcset Attributes (Manual)

```blade
<img {!! $model->getSrcset('image') !!} alt="..." class="...">
```

Returns `srcset="..." sizes="..." src="..."` attributes for manual `<img>` construction.

### Graceful Degradation

All methods handle missing conversions, legacy string paths, and null data without throwing exceptions. `getPicture()` falls back to a simple `<img>`, `getSrcset()` returns an empty string, and `getImageWithSrcset()` falls back to the original URL.

See [Responsive Images](docs/responsive-images.md) for detailed usage.

---

## Attribution

ChambreNoir stores photographer credit and portfolio link alongside image data.

### Enable in Form

```php
RetouchMediaUpload::make('image')
    ->preset(HeroConversion::class)
    ->attribution()
    ->required();
```

This adds "Credit" and "Portfolio Link" fields below the upload component.

### Requirement Control

```php
// Inherits from component's required() state (default)
->attribution()

// Force all attribution fields required
->attributionRequired(true)

// Force all optional
->attributionRequired(false)

// Granular control
->attributionRequired(['name' => true, 'link' => false])

// Dynamic
->attributionRequired(fn ($get) => $get('requires_credit'))
```

### Display in Templates

```blade
{{-- Automatic: getFigure() includes attribution as figcaption --}}
{!! $post->getFigure('image', [
    'image'   => ['alt' => $post->title],
    'caption' => ['class' => 'text-sm text-gray-600'],
]) !!}

{{-- Manual --}}
@if($post->hasAttribution('image'))
    <p>
        Photo by
        @if($link = $post->getAttributionLink('image'))
            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer">
                {{ $post->getAttributionName('image') }}
            </a>
        @else
            {{ $post->getAttributionName('image') }}
        @endif
    </p>
@endif
```

### Attribution Methods

```php
$model->getAttribution('image')      // ['name' => '...', 'link' => '...'] or null
$model->getAttributionName('image')  // string or null
$model->getAttributionLink('image')  // string or null
$model->hasAttribution('image')      // bool
```

---

## Artisan Commands

### Make Conversion

```bash
php artisan chambre-noir:make-conversion ProductImage
```

Generates `app/BlackpigCreatif/ChambreNoir/Conversions/ProductImageConversion.php` extending `BaseConversion` with a scaffolded `define()` method and responsive config.

### Regenerate Conversions

Regenerate image conversions across models, Atelier blocks, and Sceau SEO data:

```bash
# All images everywhere
php artisan chambre-noir:regenerate --all

# Only model images
php artisan chambre-noir:regenerate --models

# Only Atelier block images
php artisan chambre-noir:regenerate --blocks

# Only Sceau SEO images
php artisan chambre-noir:regenerate --seo

# Target a specific model
php artisan chambre-noir:regenerate --model="App\Models\Post"

# Target a specific block type
php artisan chambre-noir:regenerate --block-type="HeroBlock"

# Filter by field name or record ID
php artisan chambre-noir:regenerate --models --field=hero_image --id=42

# Filter by conversion class or disk
php artisan chambre-noir:regenerate --models --conversion="App\Conversions\HeroConversion"
php artisan chambre-noir:regenerate --all --disk=s3

# Preview without changes
php artisan chambre-noir:regenerate --all --dry-run

# Skip confirmation
php artisan chambre-noir:regenerate --all --force

# Backup old conversions before regenerating
php artisan chambre-noir:regenerate --all --backup

# JSON output for scripting
php artisan chambre-noir:regenerate --all --json
```

---

## Configuration

Published to `config/chambre-noir.php`.

| Key | Default | Description |
|-----|---------|-------------|
| `quality` | `90` | Global default quality (1-100). Override per-preset or per-conversion. |
| `disk` | `'public'` | Default storage disk. |
| `conversions_directory` | `'conversions'` | Subdirectory for conversion files within the upload directory. |
| `presets` | `[...]` | Legacy array-based presets. Use `BaseConversion` classes instead. |
| `responsive.default_conversion` | `'medium'` | Fallback conversion for `<img src="">` in responsive elements. |
| `responsive.default_sizes` | `null` | Default `sizes` attribute. `null` = auto-generate from widths. |
| `responsive.auto_generate_sizes` | `true` | Auto-calculate `sizes` from conversion widths and breakpoints. |
| `responsive.breakpoints` | Tailwind defaults | Breakpoints for auto-generated media queries. |

Environment variables:

```env
CHAMBRE_NOIR_QUALITY=85
CHAMBRE_NOIR_DISK=public
```

---

## File Storage Structure

```
storage/app/public/pages/hero/
    image.jpg                              # Original
    conversions/
        image-small.jpg                    # 600x600
        image-medium.jpg                   # 1200x1200
        image-large.jpg                    # 2400x2400
```

---

## Integration with Atelier

ChambreNoir is designed as a companion to [Atelier](https://github.com/blackpig-creatif/atelier). Use `RetouchMediaUpload` in block schemas and `HasRetouchMedia` on blocks:

```php
use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;

class HeroBlock extends BaseBlock
{
    use HasRetouchMedia;
    // ...
}
```

In block templates:

```blade
{!! $block->getPicture('background_image', [
    'alt'   => $block->getTranslated('title'),
    'class' => 'w-full h-full object-cover',
]) !!}
```

Image cleanup for block attributes is handled automatically by Atelier's `BlockManager` via `ImageCleanupService`.

---

## Integration with Sceau

ChambreNoir's regeneration command supports [Sceau](https://github.com/blackpig-creatif/sceau) SEO images. Use `--seo` to regenerate Open Graph and social sharing images managed by Sceau.

---

## License

MIT. See [LICENSE.md](LICENSE.md).

## Credits

- [Blackpig Creatif](https://blackpig.eu)
