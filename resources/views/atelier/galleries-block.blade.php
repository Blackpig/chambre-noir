{{-- resources/views/atelier/galleries-block.blade.php --}}
@php
    /**
     * Galleries Block (ChambreNoir)
     *
     * @var \BlackpigCreatif\ChambreNoir\Atelier\GalleriesBlock $block
     * @var array $galleriesData   [{id, title, images: [{display, lightbox, caption, ...}]}]
     * @var string $display_as     'gallery' | 'carousel'
     * @var string $multi_mode     'combined' | 'tabbed'
     * @var bool   $lightbox
     */

    $blockIdentifier  = 'chambre-noir-' . $block::getBlockIdentifier();
    $fragmentId       = $block->getFragmentId();
    $displayAs        = $display_as ?? 'gallery';
    $multiMode        = $multi_mode ?? 'combined';
    $useLightbox      = (bool) ($lightbox ?? true);
    $blockTitle       = $block->getTranslated('title');
    $blockDescription = $block->getTranslated('description');
    $galleriesJson    = \Illuminate\Support\Js::from($galleriesData);
@endphp

@once('chambre-noir-galleries-block-script')
<script>
function cnGalleriesBlock(galleriesData, displayAs, multiMode, useLightbox) {
    return {
        galleriesData,
        displayAs,
        multiMode,
        useLightbox,

        // Tabbed switcher state
        activeTab: 0,

        // Combined filter state (null = all)
        activeFilter: null,

        // Carousel state (per active image pool)
        carouselIndex: 0,

        // Lightbox state
        lightboxIndex: null,

        // ── Derived ──────────────────────────────────────────────────────────

        get isTabbed() {
            return this.multiMode === 'tabbed' && this.galleriesData.length > 1;
        },

        get hasMultiple() {
            return this.galleriesData.length > 1;
        },

        // All images flattened with gallery context (used for combined mode)
        get allImages() {
            return this.galleriesData.flatMap((g, gi) =>
                g.images.map(img => ({ ...img, _galleryIndex: gi, _galleryTitle: g.title }))
            );
        },

        // The image pool currently visible (filtered or tabbed)
        get activeImages() {
            if (this.isTabbed) {
                return (this.galleriesData[this.activeTab] ?? { images: [] }).images;
            }
            if (this.activeFilter !== null) {
                return this.allImages.filter(img => img._galleryIndex === this.activeFilter);
            }
            return this.allImages;
        },

        // ── Tabs ─────────────────────────────────────────────────────────────

        switchTab(index) {
            this.activeTab = index;
            this.carouselIndex = 0;
            this.lightboxIndex = null;
        },

        // ── Combined filter ───────────────────────────────────────────────────

        setFilter(index) {
            this.activeFilter = this.activeFilter === index ? null : index;
            this.carouselIndex = 0;
            this.lightboxIndex = null;
        },

        // ── Carousel ──────────────────────────────────────────────────────────

        get carouselImage() {
            return this.activeImages[this.carouselIndex] ?? null;
        },
        prevSlide() {
            const len = this.activeImages.length;
            this.carouselIndex = (this.carouselIndex - 1 + len) % len;
        },
        nextSlide() {
            this.carouselIndex = (this.carouselIndex + 1) % this.activeImages.length;
        },
        goToSlide(index) {
            this.carouselIndex = index;
        },

        // ── Lightbox ──────────────────────────────────────────────────────────

        openLightbox(index) {
            if (this.useLightbox) this.lightboxIndex = index;
        },
        closeLightbox() {
            this.lightboxIndex = null;
        },
        prevLightbox() {
            const len = this.activeImages.length;
            this.lightboxIndex = (this.lightboxIndex - 1 + len) % len;
        },
        nextLightbox() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.activeImages.length;
        },
        get lightboxImage() {
            return this.lightboxIndex !== null ? this.activeImages[this.lightboxIndex] : null;
        },
    };
}
</script>
@endonce

<section
    class="{{ $blockIdentifier }} {{ $block->getWrapperClasses() }}"
    @if($fragmentId) id="{{ $fragmentId }}" @endif
    data-block-type="{{ $block::getBlockIdentifier() }}"
    data-block-id="{{ $block->blockId ?? '' }}"
    x-data="cnGalleriesBlock(
        {{ $galleriesJson }},
        {{ \Illuminate\Support\Js::from($displayAs) }},
        {{ \Illuminate\Support\Js::from($multiMode) }},
        {{ $useLightbox ? 'true' : 'false' }}
    )"
>
    <div class="{{ $block->getContainerClasses() }}">

        {{-- Block-level title / description -------------------------------- --}}
        @if($blockTitle)
            <h2 class="text-3xl font-bold mb-4 text-gray-900">{{ $blockTitle }}</h2>
        @endif
        @if($blockDescription)
            <p class="text-gray-600 mb-6">{{ $blockDescription }}</p>
        @endif

        {{-- Empty state ---------------------------------------------------- --}}
        <template x-if="galleriesData.length === 0">
            <p class="text-gray-400 italic">No galleries selected.</p>
        </template>

        <template x-if="galleriesData.length > 0">
            <div>

                {{-- ── TABBED SWITCHER (multiple galleries, tabbed mode) ─────── --}}
                <template x-if="isTabbed">
                    <div>
                        {{-- Tab buttons --}}
                        <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200">
                            <template x-for="(gallery, index) in galleriesData" :key="gallery.id">
                                <button
                                    type="button"
                                    @click="switchTab(index)"
                                    :class="activeTab === index
                                        ? 'border-b-2 border-gray-900 text-gray-900 font-medium'
                                        : 'text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm transition-colors -mb-px"
                                    x-text="gallery.title"
                                ></button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ── COMBINED FILTER (multiple galleries, combined mode) ──── --}}
                <template x-if="!isTabbed && hasMultiple">
                    <div class="flex flex-wrap gap-2 mb-6">
                        <button
                            type="button"
                            @click="setFilter(null)"
                            :class="activeFilter === null
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-full text-sm transition-colors"
                        >All</button>
                        <template x-for="(gallery, index) in galleriesData" :key="gallery.id">
                            <button
                                type="button"
                                @click="setFilter(index)"
                                :class="activeFilter === index
                                    ? 'bg-gray-900 text-white'
                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="px-3 py-1 rounded-full text-sm transition-colors"
                                x-text="gallery.title"
                            ></button>
                        </template>
                    </div>
                </template>

                {{-- ── CAROUSEL ────────────────────────────────────────────── --}}
                <template x-if="displayAs === 'carousel'">
                    <div class="relative overflow-hidden">
                        <div
                            :class="useLightbox ? 'cursor-pointer' : ''"
                            @click="openLightbox(carouselIndex)"
                        >
                            <template x-for="(image, index) in activeImages" :key="index">
                                <div x-show="carouselIndex === index" x-cloak>
                                    <img
                                        :src="image.display"
                                        :alt="image.caption || ''"
                                        loading="lazy"
                                        class="w-full object-cover rounded-lg"
                                    >
                                    <template x-if="image.caption">
                                        <p class="mt-2 text-sm text-center text-gray-500" x-text="image.caption"></p>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <template x-if="activeImages.length > 1">
                            <div>
                                <button type="button" @click="prevSlide()"
                                    class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 flex items-center justify-center text-white transition-colors"
                                    aria-label="Previous">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button" @click="nextSlide()"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 flex items-center justify-center text-white transition-colors"
                                    aria-label="Next">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>

                                <div class="flex justify-center gap-2 mt-4">
                                    <template x-for="(image, index) in activeImages" :key="index">
                                        <button type="button" @click="goToSlide(index)"
                                            :class="carouselIndex === index ? 'w-3 h-3 bg-gray-800' : 'w-2 h-2 bg-gray-300 hover:bg-gray-500'"
                                            class="rounded-full transition-all duration-200"
                                            :aria-label="`Image ${index + 1}`"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ── GALLERY GRID ─────────────────────────────────────────── --}}
                <template x-if="displayAs === 'gallery'">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template x-for="(image, index) in activeImages" :key="index">
                            <div
                                class="relative overflow-hidden rounded-lg shadow-md group"
                                :class="useLightbox ? 'cursor-pointer' : ''"
                                @click="openLightbox(index)"
                            >
                                <img
                                    :src="image.display"
                                    :alt="image.caption || ''"
                                    loading="lazy"
                                    class="w-full aspect-square object-cover transition-transform duration-300 group-hover:scale-105"
                                >
                                <template x-if="useLightbox">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors duration-300 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

            </div>
        </template>

    </div>

    {{-- ── LIGHTBOX ────────────────────────────────────────────────────────── --}}
    <template x-if="useLightbox">
        <div
            x-show="lightboxIndex !== null"
            x-cloak
            @keydown.escape.window="if (lightboxIndex !== null) closeLightbox()"
            @keydown.arrow-left.window="if (lightboxIndex !== null) prevLightbox()"
            @keydown.arrow-right.window="if (lightboxIndex !== null) nextLightbox()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
            @click.self="closeLightbox()"
        >
            <button type="button" @click="closeLightbox()"
                class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/25 flex items-center justify-center text-white transition-colors"
                aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <button type="button" @click="prevLightbox()"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 flex items-center justify-center text-white transition-colors"
                aria-label="Previous">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <div class="flex flex-col items-center gap-3 max-h-[90vh] max-w-[90vw]">
                <img
                    :src="lightboxImage?.lightbox"
                    class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg shadow-2xl"
                    :alt="lightboxImage?.caption || `Image ${(lightboxIndex ?? 0) + 1} of ${activeImages.length}`"
                >
                <template x-if="lightboxImage?.caption">
                    <p class="text-white/80 text-sm text-center" x-text="lightboxImage.caption"></p>
                </template>
                <span class="text-white/50 text-xs tabular-nums"
                    x-text="lightboxIndex !== null ? `${lightboxIndex + 1} / ${activeImages.length}` : ''"></span>
            </div>

            <button type="button" @click="nextLightbox()"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/25 flex items-center justify-center text-white transition-colors"
                aria-label="Next">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </template>

    {{-- Block Divider --}}
    @if($block->getDividerComponent())
        <x-dynamic-component
            :component="$block->getDividerComponent()"
            :to-background="$block->getDividerToBackground()"
        />
    @endif

</section>
