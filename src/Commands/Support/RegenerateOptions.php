<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

class RegenerateOptions
{
    public function __construct(
        public bool $all = false,
        public bool $models = false,
        public bool $blocks = false,
        public bool $seo = false,
        public ?string $model = null,
        public ?string $blockType = null,
        public ?string $field = null,
        public ?int $id = null,
        public ?string $conversion = null,
        public ?string $disk = null,
        public bool $dryRun = false,
        public bool $force = false,
        public bool $backup = false,
        public bool $keepOnFail = true,
        public bool $verbose = false,
        public bool $quiet = false,
        public bool $json = false,
    ) {}

    /**
     * Create from command input
     */
    public static function fromCommand(\Illuminate\Console\Command $command): self
    {
        return new self(
            all: $command->option('all') ?? false,
            models: $command->option('models') ?? false,
            blocks: $command->option('blocks') ?? false,
            seo: $command->option('seo') ?? false,
            model: $command->option('model'),
            blockType: $command->option('block-type'),
            field: $command->option('field'),
            id: $command->option('id') ? (int) $command->option('id') : null,
            conversion: $command->option('conversion'),
            disk: $command->option('disk'),
            dryRun: $command->option('dry-run') ?? false,
            force: $command->option('force') ?? false,
            backup: $command->option('backup') ?? false,
            keepOnFail: $command->option('keep-on-fail') ?? true,
            verbose: $command->option('verbose') ?? false,
            quiet: $command->option('quiet') ?? false,
            json: $command->option('json') ?? false,
        );
    }

    /**
     * Should we scan regular Eloquent models?
     */
    public function shouldScanModels(): bool
    {
        return $this->all || $this->models || $this->model !== null;
    }

    /**
     * Should we scan Atelier blocks?
     */
    public function shouldScanBlocks(): bool
    {
        return $this->all || $this->blocks || $this->blockType !== null;
    }

    /**
     * Should we scan Sceau SEO data?
     */
    public function shouldScanSeo(): bool
    {
        return $this->all || $this->seo;
    }

    /**
     * Are we targeting a specific model?
     */
    public function hasModelFilter(): bool
    {
        return $this->model !== null;
    }

    /**
     * Are we targeting a specific block type?
     */
    public function hasBlockTypeFilter(): bool
    {
        return $this->blockType !== null;
    }

    /**
     * Are we targeting a specific field?
     */
    public function hasFieldFilter(): bool
    {
        return $this->field !== null;
    }

    /**
     * Are we targeting a specific record ID?
     */
    public function hasIdFilter(): bool
    {
        return $this->id !== null;
    }

    /**
     * Are we filtering by conversion class?
     */
    public function hasConversionFilter(): bool
    {
        return $this->conversion !== null;
    }

    /**
     * Are we filtering by disk?
     */
    public function hasDiskFilter(): bool
    {
        return $this->disk !== null;
    }

    /**
     * Validate options
     */
    public function validate(): array
    {
        $errors = [];

        // Must specify what to regenerate
        if (! $this->shouldScanModels() && ! $this->shouldScanBlocks() && ! $this->shouldScanSeo()) {
            $errors[] = 'Must specify --all, --models, --blocks, --seo, --model, or --block-type';
        }

        // Can't specify both --model and --block-type
        if ($this->model && $this->blockType) {
            $errors[] = 'Cannot specify both --model and --block-type';
        }

        // Can't specify --id without --model or --block-type
        if ($this->id && ! $this->model && ! $this->blockType && ! $this->seo) {
            $errors[] = '--id requires --model, --block-type, or --seo';
        }

        // Can't specify multiple type flags together
        $typeFlags = array_filter([$this->models, $this->blocks, $this->seo]);
        if (count($typeFlags) > 1) {
            $errors[] = 'Cannot specify multiple type flags together (use --all instead)';
        }

        // Can't be both quiet and verbose
        if ($this->quiet && $this->verbose) {
            $errors[] = 'Cannot specify both --quiet and --verbose';
        }

        return $errors;
    }

    /**
     * Get a human-readable description of what will be regenerated
     */
    public function getDescription(): string
    {
        $parts = [];

        if ($this->all) {
            $parts[] = 'all images';
        } else {
            if ($this->model) {
                $parts[] = "model: {$this->model}";
            } elseif ($this->models) {
                $parts[] = 'all models';
            }

            if ($this->blockType) {
                $parts[] = "block type: {$this->blockType}";
            } elseif ($this->blocks) {
                $parts[] = 'all blocks';
            }

            if ($this->seo) {
                $parts[] = 'all SEO images';
            }
        }

        if ($this->field) {
            $parts[] = "field: {$this->field}";
        }

        if ($this->id) {
            $parts[] = "ID: {$this->id}";
        }

        if ($this->conversion) {
            $parts[] = "conversion: " . class_basename($this->conversion);
        }

        if ($this->disk) {
            $parts[] = "disk: {$this->disk}";
        }

        return implode(', ', $parts);
    }
}
