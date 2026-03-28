# ChambreNoir

Automatic image conversion and optimization for Filament FileUpload fields. Provides `RetouchMediaUpload`, conversion presets, responsive image helpers, and (Phase 1) a Gallery management system.

## Package-Specific Notes

- The `image` column on `GalleryImage` stores the full ChambreNoir JSON blob (preset FQNS, original path, attribution, conversion paths)
- Gallery storage directory is derived from `Gallery->slug` at upload time: `galleries/{slug}/`
- The `panel` column on `Gallery` is auto-stamped on creation from the current Filament panel ID — do not prompt for it in forms unless the user is a super-admin
- `is_active` defaults to `false` — galleries must be explicitly published
- `GalleryPickerField` always uses a polymorphic pivot (`galleryables`) regardless of single/multiple mode
- The `conversions` config key is new in this version — read with `config('chambre-noir.conversions', [])` everywhere to avoid breaking existing published configs
- Phase 2 (bulk upload explode, Atelier GalleryBlock) is explicitly deferred — do not build it
