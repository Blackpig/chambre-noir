<?php

namespace BlackpigCreatif\ChambreNoir\Forms\Components;

use BlackpigCreatif\ChambreNoir\Models\Gallery;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

class GalleryPickerField extends Select
{
    protected bool|Closure $includeUnpublished = false;

    protected ?string $conversionFilter = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relationship('galleries', 'title');

        $this->modifyOptionsQueryUsing(function (Builder $query): Builder {
            $includeUnpublished = $this->evaluate($this->includeUnpublished);

            if (! $includeUnpublished) {
                $query->where('is_active', true);
            }

            if ($this->conversionFilter !== null) {
                $query->where('conversion', $this->conversionFilter);
            }

            return $query;
        });

        $this->getOptionLabelUsing(function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $gallery = Gallery::find($value);

            if ($gallery === null) {
                return null;
            }

            $conversionLabel = $gallery->conversion
                ? config("chambre-noir.conversions.{$gallery->conversion}.label", $gallery->conversion)
                : null;

            return $conversionLabel
                ? "{$gallery->title} ({$conversionLabel})"
                : $gallery->title;
        });
    }

    public function unpublished(bool|Closure $value = true): static
    {
        $this->includeUnpublished = $value;

        return $this;
    }

    public function conversion(string $key): static
    {
        $this->conversionFilter = $key;

        return $this;
    }
}
