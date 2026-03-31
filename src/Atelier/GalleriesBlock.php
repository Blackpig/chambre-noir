<?php

namespace BlackpigCreatif\ChambreNoir\Atelier;

use BlackpigCreatif\Atelier\Abstracts\BaseBlock;
use BlackpigCreatif\ChambreNoir\Models\Gallery;
use BlackpigCreatif\ChambreNoir\Services\ConversionManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class GalleriesBlock extends BaseBlock
{
    public static function getLabel(): string
    {
        return 'Gallery (ChambreNoir)';
    }

    public static function getDescription(): ?string
    {
        return 'Display one or more managed ChambreNoir galleries as a grid or carousel.';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function getSchema(): array
    {
        return [
            ...static::getHeaderFields(),

            Section::make('Content')
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->placeholder('Optional — overrides the gallery title')
                        ->maxLength(255)
                        ->translatable(),

                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Optional — overrides the gallery description')
                        ->rows(3)
                        ->translatable(),
                ])
                ->collapsible(),

            Section::make('Galleries')
                ->schema([
                    Select::make('gallery_ids')
                        ->label('Galleries')
                        ->options(static::galleryOptions())
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Section::make('Display')
                ->schema([
                    Group::make([
                        ToggleButtons::make('display_as')
                            ->label('Display as')
                            ->options([
                                'gallery'  => 'Gallery',
                                'carousel' => 'Carousel',
                            ])
                            ->default('gallery')
                            ->inline(),

                        Toggle::make('lightbox')
                            ->label('Use lightbox')
                            ->default(true)
                            ->helperText('Click image to view full-screen'),
                    ]),

                    ToggleButtons::make('multi_mode')
                        ->label('Multiple galleries')
                        ->options([
                            'combined' => 'Combined with filter',
                            'tabbed'   => 'Tabbed switcher',
                        ])
                        ->default('combined')
                        ->inline()
                        ->helperText('Only applies when more than one gallery is selected'),
                ])
                ->collapsible(),

            ...static::getCommonOptionsSchema(),
        ];
    }

    public static function getTranslatableFields(): array
    {
        return ['title', 'description'];
    }

    public static function getViewPath(): string
    {
        return 'chambre-noir::atelier.galleries-block';
    }

    public function render(): View
    {
        $galleryIds = array_filter((array) $this->get('gallery_ids', []));
        $galleriesData = [];

        if (! empty($galleryIds)) {
            Gallery::with(['images' => fn ($q) => $q->orderBy('sort_order')])
                ->findMany($galleryIds)
                ->sortBy(fn (Gallery $g) => array_search($g->id, $galleryIds))
                ->each(function (Gallery $gallery) use (&$galleriesData): void {
                    $images = $this->buildImagesData($gallery->images);

                    if (! empty($images)) {
                        $galleriesData[] = [
                            'id'     => $gallery->id,
                            'title'  => $gallery->title,
                            'images' => $images,
                        ];
                    }
                });
        }

        return view(static::getViewPath(), array_merge($this->getViewData(), [
            'galleriesData' => $galleriesData,
        ]));
    }

    protected function buildImagesData(Collection $images): array
    {
        $manager = app(ConversionManager::class);
        $disk = config('chambre-noir.disk', 'public');
        $result = [];

        foreach ($images as $image) {
            $data = $image->image;

            if (! is_array($data) || ! isset($data['original'])) {
                continue;
            }

            $conversionKeys = array_keys($data['conversions'] ?? []);

            if (empty($conversionKeys)) {
                continue;
            }

            $urls = [];
            foreach ($conversionKeys as $name) {
                $url = $manager->getUrl($data, $name, $disk);
                if ($url) {
                    $urls[$name] = $url;
                }
            }

            if (empty($urls)) {
                continue;
            }

            $displayUrl  = $urls['medium'] ?? $urls['small'] ?? $urls['thumb'] ?? $urls[array_key_first($urls)];
            $lightboxUrl = $urls['large'] ?? $urls[array_key_last($urls)];

            $result[] = array_merge($urls, [
                'display'  => $displayUrl,
                'lightbox' => $lightboxUrl,
                'caption'  => $image->caption,
            ]);
        }

        return $result;
    }

    protected static function galleryOptions(): array
    {
        return Gallery::orderBy('title')
            ->get()
            ->mapWithKeys(function (Gallery $gallery): array {
                $label = $gallery->is_active
                    ? $gallery->title
                    : "{$gallery->title} (inactive)";

                return [$gallery->id => $label];
            })
            ->all();
    }
}
