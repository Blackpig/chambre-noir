<div class="mt-4 space-y-3">
    <div>
        <label
            for="{{ $id }}-attribution-name"
            class="fi-fo-field-wrp-label inline-flex items-center gap-x-3"
        >
            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                Credit
            </span>
            @if ($isNameRequired)
                <span class="text-sm text-danger-600 dark:text-danger-400">*</span>
            @endif
        </label>
        <input
            id="{{ $id }}-attribution-name"
            type="text"
            wire:model="{{ $statePath }}_attribution_name"
            placeholder="e.g., John Doe or @photographer"
            @disabled($isDisabled)
            @required($isNameRequired)
            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 disabled:placeholder-gray-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500 dark:disabled:border-white/10 dark:disabled:bg-transparent dark:disabled:text-white/50 sm:text-sm sm:leading-6"
        />
    </div>

    <div>
        <label
            for="{{ $id }}-attribution-link"
            class="fi-fo-field-wrp-label inline-flex items-center gap-x-3"
        >
            <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                Portfolio Link
            </span>
            @if ($isLinkRequired)
                <span class="text-sm text-danger-600 dark:text-danger-400">*</span>
            @endif
        </label>
        <input
            id="{{ $id }}-attribution-link"
            type="url"
            wire:model="{{ $statePath }}_attribution_link"
            placeholder="https://example.com/portfolio"
            @disabled($isDisabled)
            @required($isLinkRequired)
            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 disabled:placeholder-gray-400 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-primary-500 dark:disabled:border-white/10 dark:disabled:bg-transparent dark:disabled:text-white/50 sm:text-sm sm:leading-6"
        />
    </div>
</div>
