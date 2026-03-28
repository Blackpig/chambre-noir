# ChambreNoir — Gallery Feature Planning

**Package:** `blackpig-creatif/chambre-noir`
**Namespace:** `BlackpigCreatif\ChambreNoir\`
**Feature scope:** Gallery management — models, Filament resource, relation manager, picker field
**Status:** Planning — not yet handed to Claude Code

---

## Problem Statement

The current `RetouchMediaUpload` with `multiple` enabled on parent resource records (e.g. a Gallery block on a page) creates significant overhead on every save cycle — checking for deletions, additions, and triggering conversion checks — even when no images have changed. At 20+ images this becomes noticeably slow. Additionally, there is no mechanism to attach per-image captions when using the multiple upload pattern.

---

## Proposed Solution

Introduce a first-class `Gallery` concept within ChambreNoir. A Gallery is a standalone managed entity with its own Filament resource. Images belong to Galleries as individual `GalleryImage` records, each with its own `RetouchMediaUpload` (default single behaviour), caption, and sort order.

Consuming resources (pages, blocks, etc.) reference one or more Galleries via a `GalleryPickerField`. The consuming record's save cycle does **zero** image processing — it only stores relationship IDs via a polymorphic pivot.

---

## Config

A `conversions` registry is added to the published `chambre-noir.php` config. This allows consuming apps to register their Conversion classes with human-readable labels, so that Gallery forms present a friendly select rather than raw FQNSs.

```php
// config/chambre-noir.php
'conversions' => [
    'gallery' => [
        'label' => 'Gallery',
        'class' => App\BlackpigCreatif\ChambreNoir\Conversions\GalleryConversion::class,
    ],
    'hero' => [
        'label' => 'Hero / Parallax',
        'class' => App\BlackpigCreatif\ChambreNoir\Conversions\HeroParallaxConversion::class,
    ],
    'thumbnail' => [
        'label' => 'Thumbnail',
        'class' => App\BlackpigCreatif\ChambreNoir\Conversions\ThumbnailConversion::class,
    ],
],
```

All Gallery config reads use a safe fallback: `config('chambre-noir.conversions', [])` — so existing sites that have already published the config file and not yet added the `conversions` key will not break. The Gallery conversion select will simply be empty until the key is added.

---

## Models

### `Gallery`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `title` | string | Required |
| `slug` | string | Unique, auto-generated from title, editable |
| `description` | text | Nullable |
| `conversion` | string | Config key (e.g. `'gallery'`), not FQNS |
| `panel` | string | Nullable, auto-stamped on create from current Filament panel ID |
| `is_active` | boolean | Default `false` — must be explicitly published |
| `timestamps` | | |

**Slug as storage directory:** All images in a Gallery are stored under `galleries/{slug}/`. This is derived at upload time from the Gallery's slug — no separate directory column needed.

**`is_active` default `false`:** Galleries are prepped and populated before being made available. This gates both the `GalleryPickerField` (inactive galleries hidden by default) and should be respected by frontend rendering logic.

**`panel` auto-stamping:** The `panel` column is automatically set on creation to the current Filament panel ID via a model `boot()` hook, using `??=` so an explicitly set value (e.g. `null` for a shared gallery) is never overwritten:

```php
static::creating(function (Gallery $gallery) {
    $gallery->panel ??= Filament::getCurrentPanel()?->getId();
});
```

Galleries with `panel = null` are visible across all panels — useful for shared assets. Panel-specific galleries are scoped automatically.

### `GalleryImage`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | |
| `gallery_id` | foreignId | `constrained()->cascadeOnDelete()` |
| `image` | json | ChambreNoir image JSON blob (see below) |
| `caption` | string | Nullable |
| `sort_order` | unsignedInteger | Default 0 |
| `timestamps` | | |

**Image JSON blob:** The `image` column stores the full ChambreNoir JSON structure. The `preset` key is stamped at upload time by resolving the Gallery's `conversion` config key to the FQNS. This is essential for the regenerate command — no changes to the existing pipeline required.

```json
{
  "preset": "App\\BlackpigCreatif\\ChambreNoir\\Conversions\\GalleryConversion",
  "original": "galleries/pool-installations/01KK9CY0S247Y0J313BH8NHPFT.jpg",
  "attribution": {
    "link": "https://www.instagram.com/owenpetersphotography/",
    "name": "@owenpetersphotography"
  },
  "conversions": {
    "thumb": "galleries/pool-installations/conversions/01KK9CY0S247Y0J313BH8NHPFT-thumb.jpg",
    "large": "galleries/pool-installations/conversions/01KK9CY0S247Y0J313BH8NHPFT-large.jpg"
  }
}
```

Note the `original` path prefix is `galleries/{slug}/` — derived from the parent Gallery's slug at upload time. Attribution is handled by the existing RMU attribution mechanism and stored in the JSON blob. No separate credit/attribution column is needed on `GalleryImage`.

---

## Filament Resource: `GalleryResource`

Standard Filament resource for managing Galleries.

**List page:** table with title, slug, conversion label, image count, active status toggle, created date.

**Create/Edit form:**
- `TextInput` — title (required, auto-populates slug)
- `TextInput` — slug (editable, unique validation)
- `Textarea` — description (optional)
- `Select` — conversion (options populated from `config('chambre-noir.conversions', [])`, displays label, stores key)
- `Toggle` — is_active
- `Select` — panel (visible to super-admins only — allows reassigning to another panel or setting `null` for shared; hidden for regular users)
- `GalleryImagesRelationManager` rendered below the form (edit page only)

**Query scoping:** `GalleryResource` overrides `getEloquentQuery()` to filter by the current panel, including `null` (shared) galleries:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where(function ($q) {
            $q->whereNull('panel')
              ->orWhere('panel', Filament::getCurrentPanel()->getId());
        });
}
```

This is consuming-app logic — the package ships with an unscoped base query that the consuming app overrides. `GalleryPickerField` inherits the same scoping since it queries `Gallery` through the same mechanism.

---

## Relation Manager: `GalleryImagesRelationManager`

Manages `GalleryImage` records belonging to a Gallery.

**Table view:**
- Thumbnail column (small rendered conversion)
- Caption column (inline editable)
- Sort order (reorderable via drag handle)
- Actions: Edit modal, Delete

**Create/Edit modal form:**
- `RetouchMediaUpload::make('image')` — default single behaviour
  - `->preset()` resolved from parent Gallery's `conversion` key via config registry
  - `->directory()` set to `galleries/{parent_gallery_slug}`
- `TextInput` — caption (optional)

Attribution is handled by the existing RMU attribution mechanism — no separate field needed.

**Conversions class resolution:** When the relation manager builds its form, it reads the parent Gallery's `conversion` key, looks it up in `config('chambre-noir.conversions')`, and passes the resolved FQNS to `->preset()`. The FQNS is then stamped into the image JSON as `preset` as normal — the existing regenerate pipeline is unaffected.

---

## Field: `GalleryPickerField`

A custom Filament field for use on any resource that needs to reference one or more galleries.

**Default behaviour:** Shows only active galleries (`is_active = true`).

**API:**

```php
// Single gallery, active only (default)
GalleryPickerField::make('galleries')

// Multiple galleries
GalleryPickerField::make('galleries')->multiple()

// Include inactive galleries unconditionally
GalleryPickerField::make('galleries')->unpublished()

// Include inactive galleries conditionally (e.g. admins only)
GalleryPickerField::make('galleries')->unpublished(fn() => auth()->user()->isAdmin())

// Filter to galleries using a specific conversion preset
GalleryPickerField::make('galleries')->conversion('gallery')

// Combine as needed
GalleryPickerField::make('galleries')
    ->multiple()
    ->conversion('gallery')
    ->unpublished(fn() => auth()->user()->isAdmin())
```

**`->unpublished()`** — accepts a boolean or callable. Defaults to `false`. When `true` (or callable resolves to `true`), inactive galleries are included alongside active ones, visually distinguished (greyed out or badged). Without this flag, inactive galleries never appear.

**`->conversion()`** — filters the picker to only show galleries registered with the specified conversion config key. Useful when a resource expects images in a specific format/size set.

**Display in form:** Selected gallery shown with title, conversion label badge, and image count. Optionally show first image as thumbnail preview.

---

## Relationship Strategy

`GalleryPickerField` always uses a polymorphic pivot — whether single or multiple galleries are selected is a field configuration concern, not a data model concern. This keeps consuming models consistent.

**`galleryables` pivot table:**

| Column | Type | Notes |
|--------|------|-------|
| `gallery_id` | foreignId | |
| `galleryable_id` | unsignedBigInteger | |
| `galleryable_type` | string | |
| `sort_order` | unsignedInteger | Order of galleries on the host resource |

---

## Traits & Concerns

### `HasGalleries` (host model trait)

```php
use BlackpigCreatif\ChambreNoir\Concerns\HasGalleries;

class Page extends Model
{
    use HasGalleries;
}
```

Provides:
- `galleries()` — morphToMany, ordered by pivot `sort_order`
- `activeGalleries()` — scoped to `is_active = true`
- `firstGallery()` — convenience accessor
- `galleryImages()` — flattened ordered collection across all attached galleries (potentially costly — use with caution or scope)

---

## Frontend Rendering

`GalleryImage` uses the `HasRetouchMedia` trait, giving the full ChambreNoir helper suite out of the box:

```php
use BlackpigCreatif\ChambreNoir\Concerns\HasRetouchMedia;

class GalleryImage extends Model
{
    use HasRetouchMedia;

    protected $casts = [
        'image' => 'array',
    ];
}
```

All standard helpers are immediately available on every `GalleryImage` record:

```blade
@foreach($gallery->images as $image)
    {{-- Simple URL (background images, meta tags) --}}
    {{ $image->getMediaUrl('image', 'large') }}

    {{-- Responsive picture element --}}
    {!! $image->getPicture('image', ['alt' => $image->caption, 'class' => 'w-full']) !!}

    {{-- Figure with caption + automatic attribution from JSON blob --}}
    {!! $image->getFigure('image', [
        'caption' => ['text' => $image->caption, 'class' => 'text-sm text-gray-500'],
    ]) !!}
@endforeach
```

`getFigure()` will render photographer attribution automatically from the JSON blob (`show_attribution` defaults to `true`). The `GalleryImage->caption` column maps to `caption.text` as a manual override when needed.

**Recommended eager load pattern:**

```php
$gallery->load(['images' => fn($q) => $q->orderBy('sort_order')]);
```

**Active check:** `is_active` should be respected in frontend queries — inactive galleries should produce no output. Use `activeGalleries()` from the `HasGalleries` trait on the host model rather than `galleries()` for public-facing pages.

The HappyCoulsonPools pattern — tabs that each load a different gallery — is a frontend concern. The data model naturally supports it: each tab references a different `gallery_id` or `gallery->slug`. No clever JS filtering needed, just different gallery IDs.

---

## Phase 2 (Deferred — Do Not Build Now)

**Bulk upload → explode to GalleryImage records**

A `RetouchMediaUpload` with multiple enabled on the Gallery create/edit form that, on save, iterates the uploaded files and creates individual `GalleryImage` records rather than saving an array to the Gallery model. This is the UX improvement that makes adding 20 images to a new gallery feel like a single gesture while preserving the clean per-record data model. Defer until Phase 1 is proven in production.

**Atelier `GalleryBlock`**

A ChambreNoir-provided Atelier block that wraps `GalleryPickerField` and adds a `display` option (gallery grid / carousel). This is the natural content block interface for galleries on frontend pages. Defer until Phase 1 is proven and the Atelier block chaining API is stable.

---

## Upgrade Considerations (Existing ChambreNoir Sites)

The `conversions` key is new to `chambre-noir.php`. Existing sites that have already published the config will not have it. The package reads this key with a safe default so nothing breaks — the Gallery conversion select will simply be empty until the key is added.

**Action required on upgrade:** Add the `conversions` array to your published `config/chambre-noir.php`. Document clearly in package changelog and README.

Three new migrations are required — all additive, no changes to existing tables.

---

## Multi-Panel Considerations

ChambreNoir Gallery is designed to work correctly in a shared Filament install where multiple panels serve different clients (e.g. Kate/Happy Kate Yoga and Chris/Happy Coulson Pools) with role-based panel access via `canAccessPanel`.

**How it works:**

- `panel` is stamped automatically on Gallery creation — no manual input required
- Each panel's `GalleryResource` and `GalleryPickerField` only see their own galleries plus any shared (`panel = null`) galleries
- A super-admin user (you) operating in either panel sees only that panel's galleries while in it — this is correct behaviour, not a limitation
- Galleries can be made shared by setting `panel = null` — useful for assets used across multiple client sites

**Reassigning panels:** The `panel` field is exposed in the Gallery form for super-admins only, allowing galleries to be moved between panels or promoted to shared status after creation. Regular panel users do not see this field.

**This is not full multi-tenancy** — it is a lightweight panel-scoped pattern appropriate for a small shared install. If requirements grow to true multi-tenancy, Filament's built-in `->tenant()` system would supersede this approach.

| Decision | Resolution |
|----------|-----------|
| Slug on Gallery | Yes — used as storage directory prefix |
| Storage directory | `galleries/{slug}/` — baked in, not configurable |
| Conversion storage | Config key on `Gallery`; resolves to FQNS at form build time; stamped into image JSON as `preset` |
| Attribution/credit | Handled by existing RMU mechanism in JSON blob — no separate column |
| `is_active` default | `false` — explicit publish required |
| Relationship strategy | Always polymorphic pivot (`galleryables`) |
| `->unpublished()` | Boolean or callable, defaults to `false` |
| `->conversion()` filter | Filters picker by conversion config key |
| Pivot sort order | Included — controls order of galleries on host resource |
| `panel` scoping | Auto-stamped on create; `GalleryResource` scopes by current panel + null; super-admin can reassign |
| Soft deletes | Deferred — not in Phase 1 |
| Resource registration | To be confirmed at build session |

---

## Out of Scope (This Feature)

- Global media library / asset browser
- Image cropping UI (handled by Filament's FileUpload crop extension)
- Copyright/expiry tracking
- User-facing gallery frontend components beyond basic accessors
- Video or non-image media
- Bulk caption editing UI
- `config:merge` artisan command (noted as a potential future utility package)

---

## Files to Create

```
src/
  Models/
    Gallery.php
    GalleryImage.php
  Resources/
    GalleryResource.php
    GalleryResource/
      Pages/
        ListGalleries.php
        CreateGallery.php
        EditGallery.php
      RelationManagers/
        GalleryImagesRelationManager.php
  Forms/
    Components/
      GalleryPickerField.php
  Concerns/
    HasGalleries.php
config/
  chambre-noir.php  (additions only — conversions key)
database/
  migrations/
    create_galleries_table.php
    create_gallery_images_table.php
    create_galleryables_table.php
```

---

*Phase 1 target: Gallery resource + GalleryImage relation manager + GalleryPickerField. Phase 2 (bulk upload explode + Atelier GalleryBlock) deferred.*
