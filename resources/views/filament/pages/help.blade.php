<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="help-content">
            {!! $contentHtml !!}
        </div>
    </div>

    <style>
        .help-content h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 1rem; }
        .help-content h2 { font-size: 1.25rem; font-weight: 700; margin: 2rem 0 0.75rem; padding-top: 1rem; border-top: 1px solid rgba(148, 163, 184, 0.25); }
        .help-content h2:first-child { margin-top: 0; padding-top: 0; border-top: none; }
        .help-content h3 { font-size: 1.05rem; font-weight: 600; margin: 1.5rem 0 0.5rem; }
        .help-content p { margin: 0 0 0.85rem; line-height: 1.65; }
        .help-content ul, .help-content ol { margin: 0 0 0.85rem 1.5rem; line-height: 1.65; }
        .help-content ul { list-style: disc; }
        .help-content ol { list-style: decimal; }
        .help-content li { margin-bottom: 0.25rem; }
        .help-content li > p { margin-bottom: 0.25rem; }
        .help-content strong { font-weight: 600; }
        .help-content code { font-family: ui-monospace, monospace; font-size: 0.85em; padding: 0.1em 0.35em; border-radius: 0.25rem; background: rgba(148, 163, 184, 0.15); }
        .help-content pre { margin: 0 0 1rem; padding: 0.85rem 1rem; border-radius: 0.5rem; background: rgba(148, 163, 184, 0.12); overflow-x: auto; }
        .help-content pre code { background: none; padding: 0; }
        .help-content blockquote { margin: 0 0 1rem; padding: 0.5rem 1rem; border-left: 3px solid rgba(251, 191, 36, 0.6); background: rgba(251, 191, 36, 0.08); border-radius: 0 0.375rem 0.375rem 0; }
        .help-content hr { margin: 1.5rem 0; border: none; border-top: 1px solid rgba(148, 163, 184, 0.25); }
        .help-content a { color: rgb(217, 119, 6); text-decoration: underline; }
        .help-content table { width: 100%; margin: 0 0 1rem; border-collapse: collapse; font-size: 0.9rem; }
        .help-content th, .help-content td { padding: 0.5rem 0.75rem; border: 1px solid rgba(148, 163, 184, 0.3); text-align: left; vertical-align: top; }
        .help-content th { background: rgba(148, 163, 184, 0.12); font-weight: 600; }
        .help-content em { color: rgb(107, 114, 128); }

        .dark .help-content code,
        .dark .help-content pre { background: rgba(148, 163, 184, 0.2); }
    </style>
</x-filament-panels::page>
