<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" icon="heroicon-o-check-circle"
                wire:confirm="Record in/out punches and mark the selected days as Present?">
                Mark Selected Days Present
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
