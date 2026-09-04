@php($r = $instance->requestable)
<div class="space-y-3 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="text-gray-500 dark:text-gray-400">Employee</p>
            <p class="font-medium text-gray-950 dark:text-white">
                {{ $r->employee?->employee_code }} — {{ $r->employee?->full_name }}
            </p>
        </div>
        <div>
            <p class="text-gray-500 dark:text-gray-400">Type</p>
            <p class="font-medium text-gray-950 dark:text-white">{{ $instance->workflowDefinition?->name }}</p>
        </div>
        <div>
            <p class="text-gray-500 dark:text-gray-400">Submitted</p>
            <p class="font-medium text-gray-950 dark:text-white">{{ $instance->created_at?->format('d M Y H:i') }}</p>
        </div>
        <div>
            <p class="text-gray-500 dark:text-gray-400">Current level</p>
            <p class="font-medium text-gray-950 dark:text-white">{{ $instance->current_level }}</p>
        </div>
    </div>

    @if (filled($r->reason ?? null))
        <div>
            <p class="text-gray-500 dark:text-gray-400">Reason</p>
            <p class="text-gray-950 dark:text-white">{{ $r->reason }}</p>
        </div>
    @endif

    @if (filled($r->attachment_path ?? null))
        <p class="text-xs text-primary-600 dark:text-primary-400">📎 Attachment provided</p>
    @endif

    @if ($instance->actions->isNotEmpty())
        <div>
            <p class="mb-1 text-gray-500 dark:text-gray-400">History</p>
            <ul class="space-y-1">
                @foreach ($instance->actions as $action)
                    <li class="text-xs text-gray-700 dark:text-gray-300">
                        L{{ $action->level }} · {{ ucfirst(str_replace('_', ' ', $action->action)) }}
                        by {{ $action->approver?->name ?? '—' }}
                        ({{ $action->acted_at?->format('d M Y') }})
                        @if (filled($action->remarks)) — “{{ $action->remarks }}” @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
