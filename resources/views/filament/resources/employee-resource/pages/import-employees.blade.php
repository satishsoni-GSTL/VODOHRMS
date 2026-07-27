<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            Download the template, fill in employee rows, then upload it below. Records are previewed and
            validated before anything is written &mdash; valid rows can be imported even if others fail.
        </p>

        <form wire:submit="uploadAndValidate">
            {{ $this->form }}

            <x-filament::button type="submit" class="mt-4">
                Upload &amp; Validate
            </x-filament::button>
        </form>
    </div>

    @if ($batchId)
        <div class="fi-section mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-semibold">Preview &amp; Validation Results</h3>
                <x-filament::button wire:click="confirmImport" color="success">
                    Confirm Import
                </x-filament::button>
            </div>

            {{ $this->table }}
        </div>
    @endif
</x-filament-panels::page>
