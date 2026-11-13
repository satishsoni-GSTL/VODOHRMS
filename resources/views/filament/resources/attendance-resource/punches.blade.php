@php
    $sources = ['biometric' => 'Biometric', 'mobile' => 'Mobile App', 'self' => 'WFH (self)', 'manual' => 'Manual', 'import' => 'Import'];
@endphp
@if ($punches->isEmpty())
    <p class="text-sm text-gray-500">No individual punches recorded for this day (entered manually or imported).</p>
@else
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                <th class="py-2 pr-3">Time</th>
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Source</th>
                <th class="py-2 pr-3">Location</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($punches as $punch)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pr-3 font-medium">{{ $punch->punch_time?->format('H:i:s') }}</td>
                    <td class="py-2 pr-3">{{ strtoupper($punch->punch_type) }}</td>
                    <td class="py-2 pr-3">{{ $sources[$punch->source] ?? ucfirst($punch->source) }}</td>
                    <td class="py-2 pr-3">
                        @if ($punch->hasCoordinates())
                            <a href="{{ $punch->mapUrl() }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">
                                {{ number_format($punch->latitude, 5) }}, {{ number_format($punch->longitude, 5) }}
                            </a>
                            @if ($punch->accuracy_m !== null)
                                <span class="text-gray-500">(±{{ (int) round($punch->accuracy_m) }} m)</span>
                            @endif
                            @if ($punch->location_id)
                                <div class="text-xs text-gray-500">
                                    {{ $punch->distance_m }} m from {{ $punch->location?->name }}
                                    · {{ $punch->within_geofence ? 'inside geofence' : 'outside geofence' }}
                                </div>
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
