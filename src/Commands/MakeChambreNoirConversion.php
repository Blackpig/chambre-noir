<?php

namespace BlackpigCreatif\ChambreNoir\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeChambreNoirConversion extends Command
{
    protected $signature = 'chambre-noir:make-conversion {name : The name of the conversion class}';

    protected $description = 'Create a new Chambre Noir conversion class';

    public function handle(): int
    {
        $name = $this->argument('name');

        // Parse the name to add "Conversion" suffix if not present
        $className = $this->parseClassName($name);

        // Define the path where the conversion will be created
        $directory = app_path('BlackpigCreatif/ChambreNoir/Conversions');
        $filePath = $directory . '/' . $className . '.php';

        // Check if file already exists
        if (file_exists($filePath)) {
            $this->error("Conversion [{$className}] already exists!");
            return self::FAILURE;
        }

        // Create directory if it doesn't exist
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate the file content from stub
        $stub = $this->getStub();
        $content = $this->populateStub($stub, $className);

        // Write the file
        file_put_contents($filePath, $content);

        $this->info("Conversion [{$className}] created successfully.");
        $this->line("Location: {$filePath}");

        return self::SUCCESS;
    }

    /**
     * Parse the class name to ensure it ends with "Conversion"
     */
    protected function parseClassName(string $name): string
    {
        // Remove "Conversion" suffix if present (case-insensitive)
        $name = preg_replace('/Conversion$/i', '', $name);

        // Convert to StudlyCase and add "Conversion" suffix
        return Str::studly($name) . 'Conversion';
    }

    /**
     * Get the stub file content
     */
    protected function getStub(): string
    {
        return file_get_contents(__DIR__ . '/../../stubs/conversion.stub');
    }

    /**
     * Populate the stub with the class name
     */
    protected function populateStub(string $stub, string $className): string
    {
        return str_replace('{{ class }}', $className, $stub);
    }
}
