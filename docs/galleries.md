# Gallery System

Reference for ChambreNoir's gallery management system.

---

## Overview

The Gallery system adds three tables (`galleries`, `gallery_images`, `galleryables`) and a full Filament management UI. Galleries are attached to models via a polymorphic pivot, so no foreign key columns are needed on your existing tables. Images are stored as ChambreNoir JSON blobs (same format as `RetouchMediaUpload`) and cleaned up automatically when replaced or deleted.

---

## Setup

### Publish and Run Migrations

```bash
php artisan vendor:publish --tag=chambre-noir-migrations
php artisan migrate
```

### Add the Relationship

Any model that will hold gallery attachments needs a `morphToMany` relationship pointing at the `galleryables` pivot:

```php
use BlackpigCreatif\ChambreNoir\Models\Gallery;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Page extends Model
{
    public function galleries(): MorphToMany
    {
        return $this->morphToMany(Gallery::class, 'galleryable')
            ->withPivot('sort_order')
            ->orderBy('galleryables.sort_order');
    }
}
```

The relationship name passed to `GalleryPickerField::make()` must match. A field named `galleries` expects a `galleries()` method on the model.

---

## Managing Galleries

`GalleryResource` is registered automatically and appears in the Filament sidebar. Each gallery has:

| Field | Notes |
|-------|-------|
| `title` | Required. Auto-generates the `slug` on create. |
| `slug` | URL-safe identifier, also used as the upload directory (`galleries/{slug}/`). |
| `description` | Optional. Not displayed in the resource list. |
| `conversion` | Optional. Binds the gallery to a specific conversion preset key from `config('chambre-noir.conversions')`. Used by `GalleryPickerField::conversion()` for filtering. |
| `is_active` | Defaults to `false`. Inactive galleries are hidden from `GalleryPickerField` by default. |

Images are managed through a `GalleryImagesRelationManager` on the edit page. Each image record has a `caption` field and can be reordered by dragging.

### Gallery-level Conversion Preset

The `conversion` field on a gallery is a config key, not a class name. Define named presets in `config/chambre-noir.php`:

```php
'conversions' => [
    'product' => [
        'label' => 'Product',
        'class' => \App\BlackpigCreatif\ChambreNoir\Conversions\ProductConversion::class,
    ],
],
```

When a gallery has a `conversion` set, `GalleryPickerField` appends the label to the option title (e.g. "Summer Collection (Product)").

---

## GalleryPickerField

`GalleryPickerField` extends Filament's `Select`. It writes to the `galleryables` polymorphic pivot and handles the relationship automatically.

### Basic Usage

```php
use BlackpigCreatif\ChambreNoir\Forms\Components\GalleryPickerField;

GalleryPickerField::make('galleries')
    ->multiple()
```

Single-select (one gallery):

```php
GalleryPickerField::make('galleries')
```

### API

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `->multiple()` | -- | false | Allow selecting multiple galleries. Recommended for most use cases. |
| `->unpublished()` | `bool\|Closure` | `false` | Include inactive galleries in the options list. |
| `->conversion(string)` | `string` | `null` | Filter options to galleries with a specific conversion preset key. |

All standard `Select` methods (`->required()`, `->searchable()`, `->label()`, etc.) work as normal.

### Filtering by Conversion

Useful when different parts of a site use different image dimensions:

```php
GalleryPickerField::make('product_galleries')
    ->multiple()
    ->conversion('product')

GalleryPickerField::make('hero_gallery')
    ->conversion('hero')
```

---

## Image Cleanup

Image files are cleaned up automatically at the model-observer level, not the form level.

| Event | Trigger | Behaviour |
|-------|---------|-----------|
| `GalleryImage::updating` | Image field replaced | Old original + all conversion files deleted. Attribution-only edits are ignored (path-comparison guard). |
| `GalleryImage::deleting` | Image record deleted | All files deleted synchronously before the row is removed. |
| `Gallery::deleting` | Entire gallery deleted | DB `cascadeOnDelete()` bypasses Eloquent observers on child rows. ChambreNoir captures all image data *before* the cascade, then dispatches a `CleanupGalleryImages` job to the queue. |

The queued job (`CleanupGalleryImages`) runs via whatever `QUEUE_CONNECTION` is configured. With `sync` (the Laravel default), it runs immediately.

---

## Frontend Rendering

### Direct Blade Access

`GalleryImage` uses `HasRetouchMedia`, so all standard rendering helpers are available:

```blade
@foreach($page->galleries as $gallery)
    <h2>{{ $gallery->title }}</h2>

    <div class="grid grid-cols-3 gap-4">
        @foreach($gallery->images as $image)
            {!! $image->getPicture('image', [
                'alt'     => $image->caption ?? $gallery->title,
                'loading' => 'lazy',
                'class'   => 'w-full aspect-square object-cover',
            ]) !!}
        @endforeach
    </div>
@endforeach
```

See [Responsive Images](responsive-images.md) for the full rendering API.

### URL Access

```php
// URL for a specific conversion
$image->getMediaUrl('image', 'medium')

// Original file URL
$image->getMediaUrl('image', 'original')
```

---

## Atelier Block

If [Atelier](https://github.com/blackpig-creatif/atelier) is installed, `GalleriesBlock` is registered automatically via the service provider. No configuration required.

### Block Schema

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `gallery_ids` | multiple Select | required | One or more galleries to display. |
| `display_as` | ToggleButtons | `gallery` | `gallery` (responsive grid) or `carousel` (single-image slider). |
| `lightbox` | Toggle | `true` | Click-to-expand overlay with keyboard navigation. |
| `multi_mode` | ToggleButtons | `combined` | Applies only when 2+ galleries are selected. `combined` shows all images with gallery filter pills; `tabbed` shows one gallery at a time with a tab bar. |
| `title` | TextInput | `null` | Optional block-level title. Translatable. |
| `description` | Textarea | `null` | Optional block-level description. Translatable. |

### Display Modes

**Gallery grid** (`display_as = 'gallery'`): three-column responsive grid. Images use `aspect-square object-cover`. Hover state reveals a zoom indicator when lightbox is enabled.

**Carousel** (`display_as = 'carousel'`): single full-width image with previous/next buttons and dot indicators. Resets position when switching tabs or applying a filter.

### Multi-gallery Modes

**Combined** (`multi_mode = 'combined'`): all gallery images are pooled into a single image set. Filter pills appear above the images when 2+ galleries are selected, letting the user show/hide images by gallery. "All" is always present.

**Tabbed** (`multi_mode = 'tabbed'`): each gallery is shown as an independent tab. Switching tabs resets the carousel position and closes any open lightbox.

Both modes are rendered from the same Alpine.js component. `display_as` controls the image rendering independently.

### Lightbox

The lightbox is an Alpine-driven overlay with:

- Keyboard navigation (left/right arrows, Escape to close)
- Background click to dismiss
- Image counter (`1 / 12`)
- Caption display when present
- CSS transitions (fade in/out)

The lightbox image uses the `large` conversion when available, falling back to the last available conversion.

### Image Resolution

`GalleriesBlock` resolves conversion URLs via `ConversionManager` and passes two virtual keys to the view:

| Key | Source |
|-----|--------|
| `display` | `medium` conversion, falls back to `small`, `thumb`, or first available |
| `lightbox` | `large` conversion, falls back to last available |

All named conversion URLs are also included in each image object, so custom view overrides can reference them by name.

### Customising the View

Override the view by publishing package views:

```bash
php artisan vendor:publish --tag=chambre-noir-views
```

The block view is at `resources/views/vendor/chambre-noir/atelier/galleries-block.blade.php`. The Alpine component is wrapped in `@once('chambre-noir-galleries-block-script')` to prevent duplicate script injection when multiple blocks appear on the same page.
