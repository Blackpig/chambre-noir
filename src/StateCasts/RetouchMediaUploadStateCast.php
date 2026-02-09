<?php

namespace BlackpigCreatif\ChambreNoir\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RetouchMediaUploadStateCast implements StateCast
{
    public function __construct(
        protected bool $isMultiple = false,
    ) {}

    /**
     * Get state (from component to application)
     * Called when reading state from the component during validation and dehydration
     */
    public function get(mixed $state): mixed
    {
        // For multiple files, return array of values
        if ($this->isMultiple) {
            return array_values(Arr::wrap($state));
        }

        // For single file upload, we need to handle the case where FileUpload
        // might have multiple items in state (old + new during replacement)
        if (! is_array($state)) {
            return $state;
        }

        // If state is an array (e.g., during replacement with multiple UUIDs),
        // return ONLY the values array for validation
        // FileUpload validation will see the count of this array
        $values = array_values($state);

        // For single file field, if there's more than one item,
        // this is likely old + new during replacement
        // Return only the LAST item (newest upload)
        if (count($values) > 1) {
            return end($values);
        }

        return $values[0] ?? null;
    }

    /**
     * Set state (from application to component)
     * Called when hydrating state into the component from the database
     *
     * This is where we intercept ChambreNoir JSON and extract just the file path(s)
     * so that BaseFileUpload doesn't see the nested structure
     */
    public function set(mixed $state): mixed
    {
        // First, check if the entire $state is a ChambreNoir format (single file case)
        if (is_array($state) && isset($state['original'])) {
            // This is ChambreNoir JSON - extract just the original path
            $state = $state['original'];
        }

        // Now apply standard FileUpload transformation
        $newState = [];

        foreach (Arr::wrap($state) as $key => $file) {
            if (blank($file)) {
                continue;
            }

            // For multiple files, each file might also be ChambreNoir format
            if (is_array($file) && isset($file['original'])) {
                $file = $file['original'];
            }

            // Generate UUID key for numeric keys (standard Filament behavior)
            if (is_numeric($key)) {
                $key = (string) Str::uuid();
            }

            $newState[$key] = $file;
        }

        return $newState;
    }
}
