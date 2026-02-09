<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

/**
 * Value object representing an image that needs regeneration
 */
class RegenerableImage
{
    public function __construct(
        public string $type,
        public string $field,
        public array $data,
        public string $disk = 'public',
        public ?string $model = null,
        public ?int $modelId = null,
        public ?int $blockId = null,
        public ?int $seoId = null,
        public ?string $seoableType = null,
        public ?int $seoableId = null,
    ) {}

    /**
     * Create from Eloquent model
     */
    public static function fromModel(
        string $modelClass,
        int $modelId,
        string $field,
        array $data,
        string $disk = 'public'
    ): self {
        return new self(
            type: 'model',
            field: $field,
            data: $data,
            disk: $disk,
            model: $modelClass,
            modelId: $modelId,
        );
    }

    /**
     * Create from Atelier block
     */
    public static function fromBlock(
        int $blockId,
        string $field,
        array $data,
        string $disk = 'public'
    ): self {
        return new self(
            type: 'block',
            field: $field,
            data: $data,
            disk: $disk,
            blockId: $blockId,
        );
    }

    /**
     * Create from Sceau SEO data
     */
    public static function fromSeo(
        int $seoId,
        string $seoableType,
        int $seoableId,
        string $field,
        array $data,
        string $disk = 'public'
    ): self {
        return new self(
            type: 'seo',
            field: $field,
            data: $data,
            disk: $disk,
            seoId: $seoId,
            seoableType: $seoableType,
            seoableId: $seoableId,
        );
    }

    /**
     * Is this a model-based image?
     */
    public function isModel(): bool
    {
        return $this->type === 'model';
    }

    /**
     * Is this a block-based image?
     */
    public function isBlock(): bool
    {
        return $this->type === 'block';
    }

    /**
     * Is this a SEO-based image?
     */
    public function isSeo(): bool
    {
        return $this->type === 'seo';
    }

    /**
     * Get the preset class name from image data
     */
    public function getPreset(): ?string
    {
        return $this->data['preset'] ?? null;
    }

    /**
     * Get the original image path
     */
    public function getOriginalPath(): ?string
    {
        return $this->data['original'] ?? null;
    }

    /**
     * Get existing conversions
     */
    public function getConversions(): array
    {
        return $this->data['conversions'] ?? [];
    }

    /**
     * Check if image has a preset
     */
    public function hasPreset(): bool
    {
        return !empty($this->data['preset']);
    }

    /**
     * Check if original file exists
     */
    public function hasOriginal(): bool
    {
        return !empty($this->data['original']);
    }

    /**
     * Get a unique identifier for this image
     */
    public function getIdentifier(): string
    {
        if ($this->isModel()) {
            return "{$this->model}:{$this->modelId}:{$this->field}";
        }

        if ($this->isSeo()) {
            return "seo:{$this->seoId}:{$this->field}";
        }

        return "block:{$this->blockId}:{$this->field}";
    }

    /**
     * Get a human-readable description
     */
    public function getDescription(): string
    {
        if ($this->isModel()) {
            $modelName = class_basename($this->model);
            return "{$modelName} #{$this->modelId} ({$this->field})";
        }

        if ($this->isSeo()) {
            $seoableModel = class_basename($this->seoableType);
            return "SEO for {$seoableModel} #{$this->seoableId} ({$this->field})";
        }

        return "Block #{$this->blockId} ({$this->field})";
    }
}
