# Changelog

All notable changes to the 1.x line are documented in this file. The 2.x line has its own changelog on `main`.

## v1.0.4 (2026-11-27)

Backports the Filament 5.12 fixes from 2.x for projects still on Atelier 1.x and Sceau 1.x.

### Fixed

- `RetouchMediaUpload` failed to load on current Filament 5 releases: its `hydrateState()` signature no longer matched Filament's, a fatal error on any form containing the field (backport of the v2 fix)
- `RetouchMediaUpload` uploads failed on Filament 5.12 with `Property [$undefined] not found on component`. The component shipped a copy of Filament's old file-upload Blade view, which never passed the new `statePath` config key, so files were uploaded to `undefined.<key>`. The copied view is gone. The field now renders through Filament's own markup and appends the attribution inputs in `wrapEmbeddedHtml()` (backport of v2.2.4)
- `dehydrateStateUsing` now uses Filament's injected `$component` instead of the captured `$this`, so image conversion and cleanup target the right item inside a Repeater (backport of v2.2.2)

### Changed

- Requires `filament/filament` ^5.12. Filament 4 projects stay on v1.0.3
