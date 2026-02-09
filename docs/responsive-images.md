# Responsive Images

Guide to rendering responsive images with ChambreNoir's `HasRetouchMedia` trait.

---

## Setup

Add the trait to your model and cast the image field to `array`:

```php
use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;

class Post extends Model
{
    use HasRetouchMedia;

    protected $casts = [
        'featured_image' => 'array',
    ];
}
```

The trait also works on Atelier blocks -- any class that stores ChambreNoir JSON and provides a `get()` or `getAttribute()` method.

---

## Choosing a Method

| Method | Output | Responsive | Use case |
|--------|--------|-----------|----------|
| `getFigure()` | `<figure>` with nested image + `<figcaption>` | Yes | Semantic images with captions or attribution |
| `getPicture()` | `<picture>` with `<source>` elements | Yes | Art direction (different crops per viewport) |
| `getImageWithSrcset()` | `<img>` with `srcset` + `sizes` | Yes | Resolution switching (same crop, multiple sizes) |
| `getImage()` | `<img>` | No | Specific conversion, no responsive features |
| `getSrcset()` | Attribute string only | Yes | Manual `<img>` construction |
| `getMediaUrl()` | URL string | No | Background images, meta tags, custom markup |

---

## getFigure()

Generates a semantic `<figure>` element. Includes attribution as `<figcaption>` when present.

```blade
{!! $post->getFigure('featured_image', [
    'mode'    => 'picture',
    'image'   => ['alt' => $post->title, 'class' => 'w-full h-auto'],
    'figure'  => ['class' => 'relative'],
    'caption' => ['class' => 'text-sm text-gray-600 mt-2'],
]) !!}
```

### Configuration Options

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `mode` | `string` | `'picture'` | Rendering mode: `picture`, `srcset`, or `image` |
| `conversion` | `string` | `'large'` | Conversion name (only used with `image` mode) |
| `sizes` | `string\|null` | `null` | Sizes attribute (only used with `srcset` mode) |
| `image` | `array` | `[]` | HTML attributes for the `<img>` tag |
| `figure` | `array` | `[]` | HTML attributes for the `<figure>` tag |
| `caption.text` | `string\|null` | `null` | Custom caption text (overrides attribution) |
| `caption.show_attribution` | `bool` | `true` | Include attribution in caption |
| `caption.*` | `string` | -- | Any other keys become `<figcaption>` attributes |

### Examples

```blade
{{-- Picture mode (default) --}}
{!! $post->getFigure('hero', [
    'image'   => ['alt' => 'Hero', 'class' => 'w-full'],
    'caption' => ['class' => 'text-sm text-gray-600'],
]) !!}

{{-- Srcset mode --}}
{!! $post->getFigure('content_image', [
    'mode'  => 'srcset',
    'sizes' => '(min-width: 1024px) 50vw, 100vw',
    'image' => ['alt' => 'Content', 'loading' => 'lazy'],
]) !!}

{{-- Image mode (specific conversion) --}}
{!! $post->getFigure('thumb', [
    'mode'       => 'image',
    'conversion' => 'thumb',
    'image'      => ['alt' => 'Thumbnail'],
]) !!}

{{-- Custom caption text (no attribution) --}}
{!! $post->getFigure('hero', [
    'caption' => ['text' => 'A sunset over the mountains', 'class' => 'italic'],
]) !!}

{{-- Suppress attribution --}}
{!! $post->getFigure('hero', [
    'caption' => ['show_attribution' => false],
]) !!}
```

---

## getPicture()

Generates a `<picture>` element with `<source>` elements based on the preset's `getResponsiveConfig()['picture']` media queries.

```blade
{!! $post->getPicture('hero_image', [
    'alt'           => $post->title,
    'class'         => 'w-full h-auto',
    'loading'       => 'lazy',
    'fetchpriority' => 'high',
]) !!}
```

Output:

```html
<picture>
    <source srcset="/storage/hero-desktop.jpg" media="(min-width: 1024px)">
    <source srcset="/storage/hero-medium.jpg" media="(min-width: 768px)">
    <img src="/storage/hero-medium.jpg" alt="..." class="w-full h-auto" loading="lazy" fetchpriority="high">
</picture>
```

Falls back to a simple `<img>` if no conversions exist.

---

## getImageWithSrcset()

Generates an `<img>` tag with `srcset` and `sizes` attributes for resolution switching.

```blade
{{-- Auto-generated sizes from preset --}}
{!! $post->getImageWithSrcset('gallery_image', null, ['alt' => 'Gallery', 'class' => 'rounded']) !!}

{{-- Custom sizes --}}
{!! $post->getImageWithSrcset('gallery_image', '(min-width: 1024px) 50vw, 100vw', ['alt' => 'Gallery']) !!}
```

Output:

```html
<img srcset="/storage/gallery-small.jpg 600w, /storage/gallery-medium.jpg 1200w, /storage/gallery-large.jpg 2400w"
     sizes="(min-width: 1024px) 2400px, (min-width: 768px) 1200px, 600px"
     src="/storage/gallery-medium.jpg"
     alt="Gallery"
     class="rounded">
```

Falls back to a simple `<img>` with the original URL if no conversions exist.

---

## getImage()

Simple `<img>` tag for a specific conversion. No responsive features.

```blade
{!! $post->getImage('product_image', 'thumb', ['alt' => 'Product', 'class' => 'w-32 h-32']) !!}
```

---

## getSrcset()

Returns `srcset`, `sizes`, and `src` as an attribute string. Use when constructing your own `<img>` tag.

```blade
<img {!! $post->getSrcset('hero_image') !!}
     alt="{{ $post->title }}"
     class="w-full h-auto"
     loading="lazy">
```

Returns an empty string if no conversions exist.

---

## getMediaUrl() / getMediaUrls()

Direct URL access for background images, meta tags, or any custom markup.

```blade
{{-- Single URL --}}
<div style="background-image: url('{{ $post->getMediaUrl('hero', 'large') }}')">

{{-- Original file --}}
<a href="{{ $post->getMediaUrl('image', 'original') }}">Download</a>

{{-- Multiple images --}}
@foreach($post->getMediaUrls('gallery', 'medium') as $url)
    <img src="{{ $url }}" alt="" loading="lazy">
@endforeach
```

---

## Performance Patterns

### Above-the-fold Images

```blade
{!! $page->getPicture('hero_image', [
    'alt'           => $page->title,
    'fetchpriority' => 'high',
]) !!}
```

### Lazy Loading

```blade
{!! $post->getImageWithSrcset('content_image', null, [
    'alt'      => '...',
    'loading'  => 'lazy',
    'decoding' => 'async',
]) !!}
```

### Preloading Critical Images

```blade
@php $heroUrl = $page->getMediaUrl('hero_image', 'large'); @endphp

@push('head')
    <link rel="preload" as="image" href="{{ $heroUrl }}">
@endpush
```

---

## Common Patterns

### Hero Section

```blade
<section class="relative h-screen">
    {!! $page->getFigure('hero_image', [
        'image'   => [
            'alt'           => $page->title,
            'class'         => 'absolute inset-0 w-full h-full object-cover',
            'fetchpriority' => 'high',
        ],
        'figure'  => ['class' => 'relative h-full'],
        'caption' => ['class' => 'absolute bottom-4 left-4 text-white text-sm bg-black/50 px-3 py-1 rounded'],
    ]) !!}

    <div class="relative z-10">
        {{-- Content --}}
    </div>
</section>
```

### Blog Post

```blade
<article>
    <h1>{{ $post->title }}</h1>

    {!! $post->getFigure('featured_image', [
        'image'   => ['alt' => $post->title, 'class' => 'w-full rounded-lg', 'loading' => 'lazy'],
        'caption' => ['class' => 'mt-2 text-sm text-gray-600 text-center'],
    ]) !!}

    <div class="prose">{!! $post->content !!}</div>
</article>
```

### Product Grid

```blade
<div class="grid grid-cols-3 gap-4">
    @foreach($products as $product)
        <a href="{{ route('products.show', $product) }}">
            {!! $product->getImage('image', 'thumb', [
                'alt'   => $product->name,
                'class' => 'w-full h-48 object-cover rounded',
            ]) !!}
            <h3>{{ $product->name }}</h3>
        </a>
    @endforeach
</div>
```

### Background Image with Preload

```blade
@php $bgUrl = $page->getMediaUrl('background', 'large'); @endphp

@push('head')
    <link rel="preload" as="image" href="{{ $bgUrl }}">
@endpush

<div class="bg-cover bg-center" style="background-image: url('{{ $bgUrl }}')">
    {{-- Content --}}
</div>
```

---

## Graceful Degradation

All rendering methods handle edge cases without exceptions:

| Scenario | `getPicture()` | `getImageWithSrcset()` | `getSrcset()` | `getMediaUrl()` |
|----------|---------------|----------------------|--------------|----------------|
| ChambreNoir JSON with conversions | `<picture>` | `<img srcset>` | Attribute string | URL |
| ChambreNoir JSON, no conversions | `<img>` with original | `<img>` with original | Empty string | URL (original) |
| Simple string path (legacy) | `<img>` | `<img>` | Empty string | URL |
| `null` / empty | Empty string | Empty string | Empty string | `null` |
