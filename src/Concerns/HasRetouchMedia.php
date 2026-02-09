<?php

namespace BlackpigCreatif\ChambreNoir\Concerns;

use BlackpigCreatif\ChambreNoir\Services\ConversionManager;
use BlackpigCreatif\ChambreNoir\Services\ResponsiveImageService;
use Illuminate\Support\HtmlString;

/**
 * Handles RetouchMediaUpload fields with image conversions
 * Expects JSON format: {"original": "path", "conversions": {"thumb": "path", ...}}
 */
trait HasRetouchMedia
{
    /**
     * Get media URL from RetouchMediaUpload field
     *
     * @param  string  $key  Field name
     * @param  string  $conversion  Conversion name ('original', 'thumb', 'medium', 'large', etc.')
     * @param  string  $disk  Storage disk name
     */
    public function getMediaUrl(string $key, string $conversion = 'large', string $disk = 'public'): ?string
    {
        // Use getAttribute() or direct property access for models, get() for blocks
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data) {
            return null;
        }

        // Handle ChambreNoir JSON format: {"original": "path", "conversions": {...}}
        if (is_array($data) && isset($data['original'])) {
            $manager = app(ConversionManager::class);

            return $manager->getUrl($data, $conversion, $disk);
        }

        // Fallback: handle simple string path (no conversions)
        if (is_string($data)) {
            return \Storage::disk($disk)->url($data);
        }

        return null;
    }

    /**
     * Get all media URLs for a field (for multiple file uploads)
     *
     * @param  string  $key  Field name
     * @param  string  $conversion  Conversion name
     * @param  string  $disk  Storage disk name
     * @return array<string>
     */
    public function getMediaUrls(string $key, string $conversion = 'large', string $disk = 'public'): array
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data) {
            return [];
        }

        // Single image in ChambreNoir format
        if (is_array($data) && isset($data['original'])) {
            $url = $this->getMediaUrl($key, $conversion, $disk);

            return $url ? [$url] : [];
        }

        // Multiple images - each as ChambreNoir JSON or string
        if (is_array($data)) {
            $urls = [];

            foreach ($data as $item) {
                if (is_array($item) && isset($item['original'])) {
                    $manager = app(ConversionManager::class);
                    $url = $manager->getUrl($item, $conversion, $disk);
                    if ($url) {
                        $urls[] = $url;
                    }
                } elseif (is_string($item)) {
                    $urls[] = \Storage::disk($disk)->url($item);
                }
            }

            return $urls;
        }

        return [];
    }

    /**
     * Get the original (unconverted) media URL
     */
    public function getOriginalMediaUrl(string $key, string $disk = 'public'): ?string
    {
        return $this->getMediaUrl($key, 'original', $disk);
    }

    /**
     * Get a specific conversion path (not URL)
     */
    public function getMediaPath(string $key, string $conversion = 'large'): ?string
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data || ! is_array($data)) {
            return null;
        }

        $manager = app(ConversionManager::class);

        return $manager->getPath($data, $conversion);
    }

    /**
     * Get a simple img tag (no responsive features)
     *
     * @param  string  $key  Field name
     * @param  string  $conversion  Conversion name to use
     * @param  array  $attributes  HTML attributes (alt, class, etc.)
     * @param  string  $disk  Storage disk
     * @return HtmlString Simple img tag
     */
    public function getImage(string $key, string $conversion = 'large', array $attributes = [], string $disk = 'public'): HtmlString
    {
        $url = $this->getMediaUrl($key, $conversion, $disk);

        if (! $url) {
            return new HtmlString('');
        }

        // Merge src with provided attributes
        $imgAttributes = array_merge(['src' => $url], $attributes);
        $attributeString = $this->buildAttributesString($imgAttributes);

        return new HtmlString(sprintf('<img %s>', $attributeString));
    }

    /**
     * Get srcset attributes for responsive images (srcset, sizes, src only)
     *
     * @param  string  $key  Field name
     * @param  string|null  $sizes  Sizes attribute (null = auto-generate)
     * @param  string  $disk  Storage disk
     * @return string Formatted srcset, sizes, and src attributes
     */
    public function getSrcset(string $key, ?string $sizes = null, string $disk = 'public'): string
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data || ! is_array($data) || ! isset($data['conversions'])) {
            return '';
        }

        $service = app(ResponsiveImageService::class);
        $result = $service->generateSrcset($data, $sizes, $disk);

        $attributeParts = [];

        if ($result['srcset']) {
            $attributeParts[] = sprintf('srcset="%s"', e($result['srcset']));
        }

        if ($result['sizes']) {
            $attributeParts[] = sprintf('sizes="%s"', e($result['sizes']));
        }

        if ($result['src']) {
            $attributeParts[] = sprintf('src="%s"', e($result['src']));
        }

        return implode(' ', $attributeParts);
    }

    /**
     * Get a complete img tag with srcset for responsive images
     *
     * @param  string  $key  Field name
     * @param  string|null  $sizes  Sizes attribute (null = auto-generate)
     * @param  array  $attributes  HTML attributes (alt, class, etc.)
     * @param  string  $disk  Storage disk
     * @return HtmlString Complete img tag with srcset or simple img as fallback
     */
    public function getImageWithSrcset(string $key, ?string $sizes = null, array $attributes = [], string $disk = 'public'): HtmlString
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data) {
            return new HtmlString('');
        }

        // If conversions exist, build full srcset img
        if (is_array($data) && isset($data['conversions'])) {
            $srcsetAttrs = $this->getSrcset($key, $sizes, $disk);
            $additionalAttrs = $this->buildAttributesString($attributes);

            $allAttrs = trim($srcsetAttrs . ' ' . $additionalAttrs);

            return new HtmlString(sprintf('<img %s>', $allAttrs));
        }

        // Fallback: simple img with original or string path
        $url = null;

        if (is_array($data) && isset($data['original'])) {
            $url = \Storage::disk($disk)->url($data['original']);
        } elseif (is_string($data)) {
            $url = \Storage::disk($disk)->url($data);
        }

        if (! $url) {
            return new HtmlString('');
        }

        $imgAttributes = array_merge(['src' => $url], $attributes);
        $attributeString = $this->buildAttributesString($imgAttributes);

        return new HtmlString(sprintf('<img %s>', $attributeString));
    }

    /**
     * Get picture element for responsive images with art direction
     *
     * @param  string  $key  Field name
     * @param  array  $attributes  HTML attributes for img tag
     * @param  string  $disk  Storage disk
     * @return HtmlString Picture element HTML
     */
    public function getPicture(string $key, array $attributes = [], string $disk = 'public'): HtmlString
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data || ! is_array($data) || ! isset($data['conversions'])) {
            // Fallback to simple img tag with original
            $url = is_string($data) ? \Storage::disk($disk)->url($data) : null;
            if ($url) {
                $imgAttributes = $this->buildAttributesString(array_merge(['src' => $url], $attributes));

                return new HtmlString(sprintf('<img %s>', $imgAttributes));
            }

            return new HtmlString('');
        }

        $service = app(ResponsiveImageService::class);

        return $service->generatePicture($data, $attributes, $disk);
    }

    /**
     * Build HTML attributes string
     */
    protected function buildAttributesString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $parts[] = e($key);
            } else {
                $parts[] = sprintf('%s="%s"', e($key), e($value));
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Get attribution data for a media field
     *
     * @param  string  $key  Field name
     * @return array|null Attribution array with 'name' and 'link' keys, or null
     */
    public function getAttribution(string $key): ?array
    {
        $data = method_exists($this, 'getAttribute') ? $this->getAttribute($key) : $this->get($key);

        if (! $data || ! is_array($data) || ! isset($data['attribution'])) {
            return null;
        }

        return $data['attribution'];
    }

    /**
     * Get attribution name (credit) for a media field
     *
     * @param  string  $key  Field name
     * @return string|null The credit/name value
     */
    public function getAttributionName(string $key): ?string
    {
        $attribution = $this->getAttribution($key);

        return $attribution['name'] ?? null;
    }

    /**
     * Get attribution link (portfolio URL) for a media field
     *
     * @param  string  $key  Field name
     * @return string|null The portfolio link URL
     */
    public function getAttributionLink(string $key): ?string
    {
        $attribution = $this->getAttribution($key);

        return $attribution['link'] ?? null;
    }

    /**
     * Check if a media field has attribution data
     *
     * @param  string  $key  Field name
     * @return bool True if attribution exists
     */
    public function hasAttribution(string $key): bool
    {
        return $this->getAttribution($key) !== null;
    }

    /**
     * Get a complete figure element with optional caption and attribution
     *
     * @param  string  $key  Field name
     * @param  array  $config  Configuration array with nested options
     * @param  string  $disk  Storage disk
     * @return HtmlString Figure element with image and optional caption
     *
     * Config structure:
     * [
     *     'mode' => 'picture',  // 'picture' (default), 'srcset', 'image'
     *     'conversion' => 'large',  // Only used when mode is 'image'
     *     'sizes' => null,  // Only used when mode is 'srcset'
     *     'image' => ['class' => '...', 'alt' => '...'],  // Attributes for img/picture
     *     'figure' => ['class' => 'relative'],  // Attributes for figure element
     *     'caption' => [
     *         'text' => 'Custom caption',  // Optional override, else uses attribution
     *         'class' => 'text-sm',  // Attributes for figcaption
     *         'show_attribution' => true,  // Whether to include attribution (default: true)
     *     ]
     * ]
     */
    public function getFigure(string $key, array $config = [], string $disk = 'public'): HtmlString
    {
        // Extract config with defaults
        $mode = $config['mode'] ?? 'picture';
        $conversion = $config['conversion'] ?? 'large';
        $sizes = $config['sizes'] ?? null;
        $imageAttrs = $config['image'] ?? [];
        $figureAttrs = $config['figure'] ?? [];
        $captionConfig = $config['caption'] ?? [];

        // Generate the image HTML based on mode
        $imageHtml = match ($mode) {
            'image' => $this->getImage($key, $conversion, $imageAttrs, $disk),
            'srcset' => $this->getImageWithSrcset($key, $sizes, $imageAttrs, $disk),
            'picture' => $this->getPicture($key, $imageAttrs, $disk),
            default => $this->getPicture($key, $imageAttrs, $disk),
        };

        // If no image, return empty
        if ($imageHtml->toHtml() === '') {
            return new HtmlString('');
        }

        // Build figure opening tag
        $figureAttrString = $this->buildAttributesString($figureAttrs);
        $html = sprintf('<figure%s>', $figureAttrString ? ' ' . $figureAttrString : '');

        // Add image
        $html .= $imageHtml->toHtml();

        // Build caption if configured or if attribution exists
        $showAttribution = $captionConfig['show_attribution'] ?? true;
        $captionText = $captionConfig['text'] ?? null;
        $captionAttrs = $captionConfig;
        unset($captionAttrs['text'], $captionAttrs['show_attribution']);

        $captionContent = $this->buildCaptionContent($key, $captionText, $showAttribution);

        if ($captionContent) {
            $captionAttrString = $this->buildAttributesString($captionAttrs);
            $html .= sprintf(
                '<figcaption%s>%s</figcaption>',
                $captionAttrString ? ' ' . $captionAttrString : '',
                $captionContent
            );
        }

        $html .= '</figure>';

        return new HtmlString($html);
    }

    /**
     * Build caption content with attribution if available
     */
    protected function buildCaptionContent(string $key, ?string $customText, bool $showAttribution): ?string
    {
        // If custom text provided, use it
        if ($customText) {
            return e($customText);
        }

        // If attribution disabled, return null
        if (! $showAttribution) {
            return null;
        }

        // Get attribution data
        $attribution = $this->getAttribution($key);

        if (! $attribution) {
            return null;
        }

        $name = $attribution['name'] ?? null;
        $link = $attribution['link'] ?? null;

        // If no name, no caption
        if (! $name) {
            return null;
        }

        // If link exists, wrap in anchor tag
        if ($link) {
            return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', e($link), e($name));
        }

        // Just name in a span
        return sprintf('<span>%s</span>', e($name));
    }
}
