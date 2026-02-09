<?php

namespace BlackpigCreatif\ChambreNoir\Commands\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Handles reporting during image regeneration
 */
class RegenerationReporter
{
    protected array $results = [];
    protected int $successCount = 0;
    protected int $failureCount = 0;
    protected int $skippedCount = 0;

    public function __construct(
        protected Command $command,
        protected RegenerateOptions $options,
    ) {}

    /**
     * Display initial summary
     */
    public function displaySummary(int $totalCount): void
    {
        if ($this->options->quiet || $this->options->json) {
            return;
        }

        $this->command->info('Chambre Noir - Image Regeneration');
        $this->command->line('');
        $this->command->line("Target: {$this->options->getDescription()}");
        $this->command->line("Images found: {$totalCount}");

        if ($this->options->dryRun) {
            $this->command->warn('DRY RUN MODE - No changes will be made');
        }

        $this->command->line('');
    }

    /**
     * Display progress for an image
     */
    public function displayProgress(RegenerableImage $image, int $current, int $total): void
    {
        if ($this->options->quiet || $this->options->json) {
            return;
        }

        if ($this->options->verbose) {
            $this->command->line("Processing [{$current}/{$total}]: {$image->getDescription()}");
        }
    }

    /**
     * Record result for an image
     */
    public function recordResult(RegenerableImage $image, array $result): void
    {
        $this->results[] = [
            'image' => $image->getIdentifier(),
            'description' => $image->getDescription(),
            'success' => $result['success'],
            'message' => $result['message'],
            'error' => $result['error'] ?? null,
        ];

        if ($result['success']) {
            $this->successCount++;
        } else {
            $this->failureCount++;
        }

        // Display failure in real-time (unless quiet/json)
        if (!$result['success'] && !$this->options->quiet && !$this->options->json) {
            $this->command->error("Failed: {$image->getDescription()} - {$result['message']}");
        }
    }

    /**
     * Record skipped image
     */
    public function recordSkipped(RegenerableImage $image, string $reason): void
    {
        $this->skippedCount++;

        if ($this->options->verbose && !$this->options->quiet && !$this->options->json) {
            $this->command->line("Skipped: {$image->getDescription()} - {$reason}");
        }
    }

    /**
     * Display final results
     */
    public function displayResults(): void
    {
        if ($this->options->json) {
            $this->displayJsonResults();
            return;
        }

        if ($this->options->quiet) {
            return;
        }

        $this->command->line('');
        $this->command->info('Results:');
        $this->command->line("Successfully regenerated: {$this->successCount}");

        if ($this->failureCount > 0) {
            $this->command->line("Failed: {$this->failureCount}");
        }

        if ($this->skippedCount > 0) {
            $this->command->line("Skipped: {$this->skippedCount}");
        }

        // Display failure details if verbose
        if ($this->options->verbose && $this->failureCount > 0) {
            $this->command->line('');
            $this->command->error('Failures:');
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    $this->command->line("  - {$result['description']}: {$result['message']}");
                }
            }
        }
    }

    /**
     * Display results as JSON
     */
    protected function displayJsonResults(): void
    {
        $output = [
            'success' => $this->failureCount === 0,
            'stats' => [
                'total' => count($this->results),
                'successful' => $this->successCount,
                'failed' => $this->failureCount,
                'skipped' => $this->skippedCount,
            ],
            'results' => $this->results,
        ];

        $this->command->line(json_encode($output, JSON_PRETTY_PRINT));
    }

    /**
     * Get exit code based on results
     */
    public function getExitCode(): int
    {
        return $this->failureCount > 0 ? 1 : 0;
    }
}
