# Conversion Presets

Guide to creating and customising `BaseConversion` classes for ChambreNoir image processing.

---

## Creating a Preset

Generate a scaffold:

```bash
php artisan chambre-noir:make-conversion ProductImage
```

This creates `app/BlackpigCreatif/ChambreNoir/Conversions/ProductImageConversion.php`. The suffix `Conversion` is added automatically.

### Minimal Preset

```php
<?php

namespace App\BlackpigCreatif\ChambreNoir\Conversions;

use BlackpigCreatif\ChambreNoir\Conversions\BaseConversion;

class ProductImageConversion extends BaseConversion
{
    protected function define(): array
    {
        return [
            'thumb'  => ['width' => 300, 'height' => 300, 'fit' => 'crop'],
            'medium' => ['width' => 800, 'height' => 800],
            'large'  => ['width' => 1600, 'height' => 1600],
        ];
    }
}
```

Conversions without explicit `fit` or `quality` inherit the class defaults (`contain` and `85` respectively).

---

## Conversion Properties

Each conversion entry accepts:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `width` | `int` | -- | Target width in pixels |
| `height` | `int` | -- | Target height in pixels |
| `fit` | `string` | Class `$defaultFit` | Resize strategy: `crop`, `contain`, `max`, `fill` |
| `quality` | `int` | Class `$defaultQuality` | JPEG/WebP quality (1-100) |

### Class Defaults

Override these properties on your preset class:

```php
class PortfolioConversion extends BaseConversion
{
    protected int $defaultQuality = 92;
    protected string $defaultFit = 'max';

    protected function define(): array
    {
        return [
            'thumb'  => ['width' => 400, 'height' => 400, 'fit' => 'crop'],
            'full'   => ['width' => 2400, 'height' => 2400],
        ];
    }
}
```

Here `full` inherits `max` fit and `92` quality from the class. `thumb` overrides fit to `crop`.

---

## Fit Methods

| Fit | Intervention Method | Behaviour |
|-----|-------------------|-----------|
| `crop` | `cover()` | Resize and crop to exact dimensions |
| `contain` | `contain()` | Fit within dimensions, preserve aspect ratio |
| `max` | `scale()` | Scale down only, never enlarge |
| `fill` | `resize()` | Resize to exact dimensions (may distort) |

---

## Composition with includes()

Presets can compose other presets. Included conversions are merged first; the defining class overrides conflicts.

```php
use BlackpigCreatif\ChambreNoir\Conversions\SocialImageConversion;

class HeroConversion extends BaseConversion
{
    protected function includes(): array
    {
        return [SocialImageConversion::class];
    }

    protected function define(): array
    {
        return [
            'mobile'  => ['width' => 768,  'height' => 1024, 'fit' => 'crop'],
            'desktop' => ['width' => 1920, 'height' => 1080, 'fit' => 'max'],
        ];
    }
}
```

The resulting preset produces `og`, `twitter` (from `SocialImageConversion`), `mobile`, and `desktop` conversions.

---

## Responsive Configuration

Override `getResponsiveConfig()` to control how `HasRetouchMedia` renders `<picture>` elements and `srcset` attributes.

```php
public function getResponsiveConfig(): array
{
    return [
        // Fallback conversion for <img src="">
        'default' => 'desktop',

        // Which conversions appear in srcset
        'srcset' => [
            'mobile'  => true,
            'desktop' => true,
        ],

        // <source> elements with media queries for <picture>
        'picture' => [
            'desktop' => '(min-width: 1024px)',
            'mobile'  => null, // fallback <img>
        ],

        // sizes attribute (null = auto-generate from widths and config breakpoints)
        'sizes' => '(min-width: 1024px) 1920px, 768px',
    ];
}
```

If `getResponsiveConfig()` is not overridden, the base class generates a default config that includes all conversions in `srcset` with no media queries.

### Auto-generated sizes

When `sizes` is `null` and `responsive.auto_generate_sizes` is `true` in config, ChambreNoir auto-generates the attribute from conversion widths matched against configured breakpoints.

---

## Fluent Interface

Customise presets at runtime with `withQuality()` and `withFit()`:

```php
RetouchMediaUpload::make('image')
    ->preset(
        (new ProductImageConversion)
            ->withQuality(95)
            ->withFit('crop')
    )
```

---

## Built-in Preset: SocialImageConversion

Ships with the package. Produces two conversions optimised for social sharing:

| Conversion | Dimensions | Fit | Quality |
|-----------|-----------|-----|---------|
| `og` | 1200 x 630 | crop | 90 |
| `twitter` | 1200 x 600 | crop | 90 |

Responsive features are disabled (social platforms fetch images directly).

---

## Preset Examples

### Avatar

```php
class AvatarConversion extends BaseConversion
{
    protected int $defaultQuality = 85;
    protected string $defaultFit = 'crop';

    protected function define(): array
    {
        return [
            'small'  => ['width' => 32,  'height' => 32],
            'medium' => ['width' => 64,  'height' => 64],
            'large'  => ['width' => 128, 'height' => 128],
            'xlarge' => ['width' => 256, 'height' => 256],
        ];
    }

    public function getResponsiveConfig(): array
    {
        return [
            'default' => 'medium',
            'srcset'  => ['small' => true, 'medium' => true, 'large' => true, 'xlarge' => true],
            'picture' => [],
            'sizes'   => '64px',
        ];
    }
}
```

### Blog Featured Image

```php
class BlogImageConversion extends BaseConversion
{
    protected function define(): array
    {
        return [
            'card'    => ['width' => 400, 'height' => 225, 'fit' => 'crop'],
            'content' => ['width' => 1200, 'height' => 675, 'fit' => 'max', 'quality' => 90],
        ];
    }

    public function getResponsiveConfig(): array
    {
        return [
            'default' => 'content',
            'srcset'  => ['card' => true, 'content' => true],
            'picture' => [
                'content' => '(min-width: 768px)',
                'card'    => null,
            ],
            'sizes' => '(min-width: 768px) 1200px, 100vw',
        ];
    }
}
```

### Gallery

```php
class GalleryConversion extends BaseConversion
{
    protected int $defaultQuality = 85;

    protected function define(): array
    {
        return [
            'thumb'  => ['width' => 200, 'height' => 200, 'fit' => 'crop'],
            'medium' => ['width' => 800, 'height' => 600, 'fit' => 'contain'],
            'large'  => ['width' => 1600, 'height' => 1200, 'fit' => 'max'],
        ];
    }

    public function getResponsiveConfig(): array
    {
        return [
            'default' => 'medium',
            'srcset'  => ['thumb' => true, 'medium' => true, 'large' => true],
            'picture' => [
                'large'  => '(min-width: 1024px)',
                'medium' => '(min-width: 640px)',
                'thumb'  => null,
            ],
            'sizes' => null,
        ];
    }
}
```

---

## Testing Presets

```php
public function test_conversions_are_defined(): void
{
    $preset = new ProductImageConversion;
    $conversions = $preset->toArray();

    $this->assertArrayHasKey('thumb', $conversions);
    $this->assertArrayHasKey('medium', $conversions);
    $this->assertEquals('crop', $conversions['thumb']['fit']);
}

public function test_defaults_are_applied(): void
{
    $preset = new ProductImageConversion;
    $conversions = $preset->toArray();

    // Every conversion has quality and fit set
    foreach ($conversions as $conversion) {
        $this->assertArrayHasKey('quality', $conversion);
        $this->assertArrayHasKey('fit', $conversion);
    }
}

public function test_responsive_config(): void
{
    $preset = new ProductImageConversion;
    $config = $preset->getResponsiveConfig();

    $this->assertArrayHasKey('default', $config);
    $this->assertArrayHasKey('srcset', $config);
    $this->assertArrayHasKey('picture', $config);
}
```
