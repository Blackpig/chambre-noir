<?php

namespace BlackpigCreatif\ChambreNoir\Commands;

use BlackpigCreatif\ChambreNoir\Commands\Support\ImageDiscoveryService;
use BlackpigCreatif\ChambreNoir\Commands\Support\ImageRegenerationService;
use BlackpigCreatif\ChambreNoir\Commands\Support\RegenerableImage;
use BlackpigCreatif\ChambreNoir\Commands\Support\RegenerateOptions;
use BlackpigCreatif\ChambreNoir\Commands\Support\RegenerationReporter;
use Illuminate\Console\Command;

class RegenerateChambreNoirImages extends Command
{
    protected $signature = 'chambre-noir:regenerate
        {--all : Regenerate all images (models, blocks, and SEO)}
        {--models : Regenerate images from all models}
        {--blocks : Regenerate images from all blocks}
        {--seo : Regenerate images from Sceau SEO data}
        {--model= : Target specific model class}
        {--block-type= : Target specific block type}
        {--field= : Target specific field name}
        {--id= : Target specific record ID}
        {--conversion= : Filter by conversion class name}
        {--disk= : Filter by disk}
        {--dry-run : Preview changes without making them}
        {--force : Skip confirmation prompts}
        {--backup : Backup old conversions before regenerating}
        {--keep-on-fail : Keep old conversions if regeneration fails (default: true)}
        {--json : Output results as JSON}';

    protected $description = 'Regenerate Chambre Noir image conversions';

    public function __construct(
        protected ImageDiscoveryService $discoveryService,
        protected ImageRegenerationService $regenerationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Parse and validate options
        $options = RegenerateOptions::fromCommand($this);
        $errors = $options->validate();

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Create reporter
        $reporter = new RegenerationReporter($this, $options);

        // Discover images
        $images = $this->discoveryService->discover($options);

        if ($images->isEmpty()) {
            if (!$options->quiet && !$options->json) {
                $this->warn('No images found matching criteria.');
            }
            return 0;
        }

        // Display summary
        $reporter->displaySummary($images->count());

        // Confirm unless force or dry-run
        if (!$options->force && !$options->dryRun && !$options->quiet) {
            if (!$this->confirm("Regenerate {$images->count()} image(s)?", true)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Process images
        $images->each(function (RegenerableImage $image, int $index) use ($options, $reporter, $images) {
            $current = $index + 1;
            $total = $images->count();

            // Display progress
            $reporter->displayProgress($image, $current, $total);

            // Skip if dry-run
            if ($options->dryRun) {
                $reporter->recordSkipped($image, 'dry-run mode');
                return;
            }

            // Regenerate
            try {
                $result = $this->regenerationService->regenerate($image, $options);

                // Debug output in verbose mode
                if ($options->verbose) {
                    $this->line("  Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED'));
                    if (!$result['success']) {
                        $this->line("  Error: {$result['message']}");
                    }
                }
            } catch (\Exception $e) {
                $result = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => 'exception',
                ];

                if ($options->verbose) {
                    $this->error("  Exception: {$e->getMessage()}");
                    $this->line($e->getTraceAsString());
                }
            }

            // Record result
            $reporter->recordResult($image, $result);
        });

        // Display results
        $reporter->displayResults();

        return $reporter->getExitCode();
    }
}
