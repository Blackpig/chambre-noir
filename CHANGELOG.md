# Changelog

All notable changes to this project will be documented in this file.

## v2.2.2 — 2026-07-01

### Fixed

- `dehydrateStateUsing` closure now uses Filament's injected `$component` parameter instead of the captured `$this`. When `RetouchMediaUpload` is inside a Filament Repeater, `cloneComponents()` mutates the original component's `$container` on every iteration, leaving all closures pointing to the last item's record — causing the last-uploaded image's files to be deleted by the other items' cleanup logic. The injected `$component` is the correct per-item clone, so cleanup and conversion now target the right record in all cases

## v2.2.0 — 2026-05-26

### Added

- Translatable `title` field on `Gallery` model (EN/FR via `spatie/laravel-translatable`)
- Translatable `caption` field on `GalleryImage` model (EN/FR)
- New migration `2026_05_26_000001_make_gallery_titles_translatable` to convert existing string columns to JSON in-place, preserving existing English values
- Locale tabs (English / Français) in the Gallery form and the Gallery Images relation manager
- `spatie/laravel-translatable` added as a package dependency

### Changed

- `galleries.title` and `gallery_images.caption` column definitions updated to `json` in the base create migrations (for new installs)
- Slug generation updated to read the EN translation when auto-generating from title

## v2.1.0 — 2026-03-28

### Added

- First-class Gallery management: `Gallery` and `GalleryImage` models, Filament resource, relation manager, and `GalleryPickerField`
- Per-image sort order (drag-to-reorder in the relation manager)
- Conversion preset registry in config — galleries reference a named preset rather than a raw FQCN

## v2.0.0 — 2026-01-15

### Changed

- Filament v5 and Laravel 13 support
- Dropped support for Laravel 11 and PHP 8.2

## v1.0.1

### Fixed

- Aggressive image cleanup wiping fields on re-save

## v1.0.0

- Initial release
