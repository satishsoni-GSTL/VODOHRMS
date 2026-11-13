<x-filament-panels::page>
    @php
        $completion = $this->completion();
        $documents = $this->documents();
        $complete = $completion['percent'] >= 100;
    @endphp

    {{-- Inline colours: the panel has no custom Tailwind build; Filament exposes its palette as CSS variables. --}}
    <x-filament::section>
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold">Profile {{ $completion['percent'] }}% complete</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $completion['done'] }} of {{ $completion['total'] }} items filled in.
                    @unless ($complete) Fill in the missing details below and upload your documents. @endunless
                </p>
            </div>
            <div style="width:100%;max-width:300px;height:10px;overflow:hidden;border-radius:9999px;background:rgba(var(--gray-400), .25)">
                <div style="height:10px;border-radius:9999px;width:{{ $completion['percent'] }}%;background:rgb(var({{ $complete ? '--success-500' : '--primary-500' }}))"></div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" style="margin-top:16px">
            @foreach ($completion['sections'] as $key => $section)
                @php $done = $section['done'] === $section['total']; @endphp
                <a href="#{{ $key }}" class="rounded-lg text-sm" style="display:block;padding:10px;border:1px solid rgba(var(--gray-400), .35)">
                    <div class="flex items-center justify-between font-medium">
                        <span>{{ $section['label'] }}</span>
                        <span style="color:rgb(var({{ $done ? '--success-600' : '--warning-600' }}))">{{ $section['done'] }}/{{ $section['total'] }}</span>
                    </div>
                    @unless ($done)
                        <div class="text-xs text-gray-500 dark:text-gray-400" style="margin-top:2px">
                            Missing: {{ collect($section['items'])->where('done', false)->pluck('label')->implode(', ') }}
                        </div>
                    @endunless
                </a>
            @endforeach
        </div>
    </x-filament::section>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">Save profile</x-filament::button>
        </div>
    </form>

    <div id="documents">
        <x-filament::section>
            <x-slot name="heading">Documents</x-slot>
            <x-slot name="description">Photo (JPG, PNG) or PDF, up to 10 MB each. Documents marked * are required.</x-slot>
            <x-slot name="headerEnd">{{ $this->uploadDocumentAction }}</x-slot>

            <div class="text-sm">
                @foreach (\App\Models\EmployeeDocument::types() as $type => $label)
                    @php
                        $uploaded = $documents->where('document_type', $type);
                        $required = array_key_exists($type, \App\Models\EmployeeDocument::REQUIRED_TYPES);
                    @endphp
                    @if ($required || $uploaded->isNotEmpty())
                        <div class="flex flex-wrap items-center justify-between gap-2" style="padding:12px 0;border-top:1px solid rgba(var(--gray-400), .25)">
                            <div>
                                <div class="font-medium">{{ $label }}@if ($required)<span style="color:rgb(var(--danger-600))"> *</span>@endif</div>
                                @forelse ($uploaded as $doc)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('employee-documents.download', $doc) }}" target="_blank" style="color:rgb(var(--primary-600))">{{ $doc->file_name }}</a>
                                        @if ($doc->is_verified)
                                            <x-filament::badge color="success" size="sm">Verified</x-filament::badge>
                                        @else
                                            <x-filament::badge color="gray" size="sm">Awaiting HR check</x-filament::badge>
                                            <x-filament::link tag="button" color="danger" size="sm"
                                                              wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Remove this document?">Remove</x-filament::link>
                                        @endif
                                    </div>
                                @empty
                                    <div style="color:rgb(var(--warning-600))">Not uploaded yet</div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
