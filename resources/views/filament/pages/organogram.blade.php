<x-filament-panels::page>
    {{-- Self-contained styles: the panel has no custom Tailwind build. --}}
    <style>
        .org { --org-line: #cbd5e1; --org-card: #fff; --org-muted: #6b7280; --org-ring: rgba(3,7,18,.10); --org-danger-bg: #fef2f2; --org-danger: #b91c1c;
               --org-warn-bg: #fffbeb; --org-warn: #b45309; --org-chip: #f3f4f6; --org-match: #fde68a; --org-me: #0F7A78; --org-canvas: #f8fafc; }
        .dark .org { --org-line: #475569; --org-card: #111827; --org-muted: #9ca3af; --org-ring: rgba(255,255,255,.12); --org-danger-bg: rgba(185,28,28,.15); --org-danger: #fca5a5;
               --org-warn-bg: rgba(180,83,9,.15); --org-warn: #fcd34d; --org-chip: #1f2937; --org-match: rgba(234,179,8,.45); --org-me: #2dd4bf; --org-canvas: #0b1120; }

        /* Toolbar */
        .org-panel { border-radius: .75rem; background: var(--org-card); box-shadow: 0 0 0 1px var(--org-ring); padding: 1rem; }
        .org-toolbar { display: flex; flex-wrap: wrap; gap: .75rem; align-items: flex-end; }
        .org-toolbar label { display: block; font-size: .72rem; font-weight: 600; margin-bottom: .25rem; color: var(--org-muted); text-transform: uppercase; letter-spacing: .03em; }
        .org-toolbar select, .org-toolbar input { border-radius: .5rem; font-size: .875rem; min-width: 10rem; }
        .org-seg { display: inline-flex; gap: .2rem; padding: .2rem; border-radius: .6rem; background: var(--org-chip); }
        .org-seg button { padding: .35rem .8rem; border-radius: .45rem; font-size: .82rem; font-weight: 500; color: var(--org-muted); }
        .org-seg button[aria-pressed="true"] { background: var(--org-card); color: inherit; box-shadow: 0 1px 2px rgba(0,0,0,.1); }
        .org-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
        .org-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; padding: .12rem .5rem; border-radius: 9999px; background: var(--org-chip); color: var(--org-muted); white-space: nowrap; }
        .org-chip.c-danger { background: var(--org-danger-bg); color: var(--org-danger); }
        .org-chip.c-warn { background: var(--org-warn-bg); color: var(--org-warn); }
        .org-dot { width: .6rem; height: .6rem; border-radius: 9999px; display: inline-block; }
        .org-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: .82rem; color: var(--org-muted); }
        .org-crumbs button { color: var(--org-me); font-weight: 500; }

        /* Canvas */
        .org-canvas { position: relative; border-radius: .75rem; background: var(--org-canvas); box-shadow: 0 0 0 1px var(--org-ring);
                      overflow: auto; max-height: calc(100vh - 14rem); min-height: 22rem; cursor: grab; }
        .org-canvas.dragging { cursor: grabbing; user-select: none; }
        .org-zoom { position: sticky; top: .6rem; left: .6rem; z-index: 5; display: inline-flex; gap: .2rem; padding: .2rem; border-radius: .6rem; background: var(--org-card); box-shadow: 0 0 0 1px var(--org-ring), 0 2px 6px rgba(0,0,0,.06); }
        .org-zoom button { width: 2rem; height: 2rem; border-radius: .45rem; font-weight: 600; color: var(--org-muted); }
        .org-zoom button:hover { background: var(--org-chip); color: inherit; }
        .org-zoom span { align-self: center; font-size: .72rem; color: var(--org-muted); min-width: 2.6rem; text-align: center; }

        /* Top-down chart: classic connector lines drawn with borders */
        .oc { display: inline-block; min-width: 100%; padding: 1.5rem 2rem 2.5rem; text-align: center; }
        .oc ul { display: flex; justify-content: center; position: relative; padding: 1.4rem 0 0; margin: 0; }
        .oc > ul { padding-top: 0; }
        .oc li { list-style: none; position: relative; padding: 1.4rem .45rem 0; display: flex; flex-direction: column; align-items: center; }
        .oc > ul > li { padding-top: 0; }
        .oc li::before, .oc li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 1.4rem; border-top: 1.5px solid var(--org-line); }
        .oc li::after { right: auto; left: 50%; border-left: 1.5px solid var(--org-line); }
        .oc li:only-child::before, .oc li:only-child::after { display: none; }
        .oc li:only-child { padding-top: 0; }
        .oc li:first-child::before, .oc li:last-child::after { border: 0 none; }
        .oc li:last-child::before { border-right: 1.5px solid var(--org-line); border-radius: 0 .5rem 0 0; }
        .oc li:first-child::after { border-radius: .5rem 0 0 0; }
        .oc ul ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 1.4rem; border-left: 1.5px solid var(--org-line); }
        .oc > ul > li::before, .oc > ul > li::after { display: none; }
        /* Stacked column for a team with no further reports */
        .oc ul.oc-stack { flex-direction: column; align-items: center; gap: .6rem; }
        .oc ul.oc-stack > li { padding: 0; }
        .oc ul.oc-stack > li::before, .oc ul.oc-stack > li::after { display: none; }
        .oc ul.oc-stack::after { content: ''; position: absolute; top: 1.4rem; bottom: 2.5rem; left: 50%; border-left: 1.5px solid var(--org-line); z-index: 0; }

        .oc-card { position: relative; z-index: 1; width: 13.5rem; text-align: left; border-radius: .7rem; background: var(--org-card);
                   box-shadow: 0 0 0 1px var(--org-ring), 0 1px 3px rgba(0,0,0,.06); overflow: hidden; transition: box-shadow .15s, transform .15s; }
        .oc-card:hover { box-shadow: 0 0 0 1px var(--org-ring), 0 6px 16px rgba(0,0,0,.12); transform: translateY(-1px); }
        .oc-stripe { height: .3rem; }
        .oc-main { display: flex; gap: .6rem; padding: .55rem .65rem .5rem; align-items: center; }
        .oc-avatar { flex: none; width: 2.5rem; height: 2.5rem; border-radius: 9999px; display: grid; place-items: center; font-size: .8rem; font-weight: 700; color: #fff; overflow: hidden; }
        .oc-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .oc-text { min-width: 0; flex: 1; }
        .oc-name, .oc-role, .oc-meta { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .oc-name { font-weight: 650; font-size: .86rem; line-height: 1.2; }
        .oc-role { font-size: .76rem; line-height: 1.25; }
        .oc-meta { font-size: .68rem; color: var(--org-muted); line-height: 1.3; }
        .oc-foot { display: flex; flex-wrap: wrap; gap: .25rem; padding: 0 .65rem .5rem; min-height: .5rem; }
        .oc-tools { position: absolute; top: .45rem; right: .4rem; display: none; gap: .15rem; }
        .oc-card:hover .oc-tools { display: flex; }
        .oc-tools a, .oc-tools button { width: 1.5rem; height: 1.5rem; display: grid; place-items: center; border-radius: .4rem; background: var(--org-chip); color: var(--org-muted); font-size: .75rem; }
        .oc-tools a:hover, .oc-tools button:hover { color: inherit; }
        .oc-card.tone-danger { background: var(--org-danger-bg); box-shadow: 0 0 0 1.5px var(--org-danger); }
        .oc-card.tone-danger .oc-name { color: var(--org-danger); letter-spacing: .04em; }
        .oc-card.tone-warning { box-shadow: 0 0 0 1.5px var(--org-warn); }
        .oc-card.is-me { box-shadow: 0 0 0 2.5px var(--org-me), 0 4px 12px rgba(0,0,0,.08); }
        .oc-card.is-match { box-shadow: 0 0 0 3px var(--org-match), 0 4px 12px rgba(0,0,0,.08); }
        .oc-toggle { position: relative; z-index: 2; margin-top: -.55rem; min-width: 1.6rem; height: 1.1rem; padding: 0 .35rem; border-radius: 9999px;
                     font-size: .66rem; font-weight: 700; background: var(--org-card); color: var(--org-muted); box-shadow: 0 0 0 1px var(--org-ring); }
        .oc-toggle:hover { color: inherit; }

        /* Indented list layout */
        .org-tree, .org-tree ul { list-style: none; margin: 0; padding: 0; }
        .org-tree ul { margin-left: 1.25rem; padding-left: 1rem; border-left: 1px solid var(--org-line); }
        .org-tree li { position: relative; margin: .4rem 0; }
        .org-tree ul > li::before { content: ""; position: absolute; left: -1rem; top: 1.6rem; width: .9rem; border-top: 1px solid var(--org-line); }
        .org-tree summary { list-style: none; cursor: pointer; }
        .org-tree summary::-webkit-details-marker { display: none; }
        .org-card { display: flex; align-items: center; gap: .75rem; padding: .55rem .8rem; border-radius: .75rem; background: var(--org-card); box-shadow: 0 0 0 1px var(--org-ring); max-width: 34rem; border-left: 4px solid var(--stripe, transparent); }
        .org-card.tone-danger { background: var(--org-danger-bg); box-shadow: 0 0 0 1px var(--org-danger); }
        .org-card.tone-warning { box-shadow: 0 0 0 1px var(--org-warn); }
        .org-card.is-match { box-shadow: 0 0 0 2px var(--org-match); }
        .org-card.is-me { box-shadow: 0 0 0 2px var(--org-me); }
        .org-avatar { flex: none; width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: grid; place-items: center; font-size: .75rem; font-weight: 600; color: #fff; overflow: hidden; }
        .org-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .org-body { min-width: 0; flex: 1; }
        .org-title { font-weight: 600; font-size: .9rem; line-height: 1.2; }
        .tone-danger .org-title { color: var(--org-danger); letter-spacing: .03em; }
        .org-sub { font-size: .8rem; line-height: 1.2; }
        .org-meta { font-size: .72rem; color: var(--org-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .org-side { display: flex; flex-direction: column; align-items: flex-end; gap: .2rem; flex: none; }
        .org-toggle { flex: none; width: 1rem; color: var(--org-muted); transition: transform .15s; font-size: .7rem; }
        details[open] > summary .org-toggle { transform: rotate(90deg); }

        @media (max-width: 640px) { .org-tree ul { margin-left: .5rem; padding-left: .6rem; } .org-tree ul > li::before { left: -.6rem; width: .5rem; } .org-side { display: none; } }
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .org-noprint { display: none !important; }
            .fi-main { padding: 0 !important; max-width: none !important; }
            .org-canvas { max-height: none; overflow: visible; box-shadow: none; background: #fff; }
            .oc-card { box-shadow: 0 0 0 1px #94a3b8; break-inside: avoid; }
        }
    </style>

    <div class="org space-y-4">
        {{-- Controls --}}
        <div class="org-panel space-y-4 org-noprint">
            <div class="org-row">
                @if (count($modes) > 1)
                    <div class="org-seg" role="group" aria-label="What to show">
                        @foreach ($modes as $key => $label)
                            <button type="button" wire:click="setMode('{{ $key }}')" aria-pressed="{{ $mode === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                @else
                    <span></span>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    <div class="org-seg" role="group" aria-label="Layout">
                        <button type="button" wire:click="setLayout('chart')" aria-pressed="{{ $display === 'chart' ? 'true' : 'false' }}">Chart</button>
                        <button type="button" wire:click="setLayout('list')" aria-pressed="{{ $display === 'list' ? 'true' : 'false' }}">List</button>
                    </div>
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-pointing-out" wire:click="expandAll">Expand all</x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-pointing-in" wire:click="collapseAll">Collapse</x-filament::button>
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-printer" x-on:click="window.print()">Print</x-filament::button>
                </div>
            </div>

            <div class="org-toolbar">
                <div>
                    <label for="org-search">Search</label>
                    <input id="org-search" type="search" wire:model.live.debounce.400ms="search" placeholder="Name, code, designation, HQ…" class="fi-input border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>

                @if ($companies->isNotEmpty() && $mode !== 'geography')
                    <div>
                        <label for="org-company">Company</label>
                        <select id="org-company" wire:model.live="companyId" class="border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">All companies</option>
                            @foreach ($companies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endif

                @if ($mode === 'people' && $departments->isNotEmpty())
                    <div>
                        <label for="org-department">Department</label>
                        <select id="org-department" wire:model.live="departmentId" class="border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">All departments</option>
                            @foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endif

                @if ($fieldForce && $mode === 'people')
                    <div>
                        <label for="org-workforce">Workforce</label>
                        <select id="org-workforce" wire:model.live="workforceType" class="border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Everyone</option>
                            <option value="ho">Head Office</option>
                            <option value="field">Field Force</option>
                            <option value="none">Not classified</option>
                        </select>
                    </div>
                @endif

                @if ($fieldForce && $divisions->isNotEmpty())
                    <div>
                        <label for="org-division">Division</label>
                        <select id="org-division" wire:model.live="divisionId" class="border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">All divisions</option>
                            @foreach ($divisions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="org-row">
                <div class="flex flex-wrap gap-2">
                    <span class="org-chip">{{ number_format($stats['total']) }} {{ $mode === 'geography' ? 'geographies' : ($mode === 'positions' ? 'positions' : 'people') }}</span>
                    @if ($mode === 'positions')
                        <span class="org-chip {{ $stats['vacant'] ? 'c-danger' : '' }}">{{ number_format($stats['vacant']) }} vacant</span>
                    @endif
                    @if ($stats['roots'] > 1 && $mode === 'people' && ! $focus)
                        <span class="org-chip">{{ $stats['roots'] }} top-level (no reporting manager in view)</span>
                    @endif
                </div>
                @if ($legend !== [] && count($legend) <= 14)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($legend as $label => $color)
                            <span class="org-chip"><span class="org-dot" style="background: {{ $color }}"></span>{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($reportingLine)
                <p class="org-crumbs">Your reporting line: {{ implode(' › ', $reportingLine) }} › <strong>You</strong></p>
            @endif

            @if ($focus)
                <div class="org-crumbs">
                    <button type="button" wire:click="clearFocus">Whole organisation</button>
                    @foreach ($breadcrumb as $crumb)
                        <span>›</span><button type="button" wire:click="focusOn('{{ $crumb['key'] }}')">{{ $crumb['title'] }}</button>
                    @endforeach
                    <span>›</span><strong>{{ $tree[0]['title'] ?? '' }}</strong>
                </div>
            @endif
        </div>

        {{-- Chart / list --}}
        @if ($tree === [])
            <div class="org-panel text-sm text-gray-500 dark:text-gray-400">
                @if (trim($search) !== '')
                    Nothing matches "{{ $search }}".
                @elseif ($mode === 'positions')
                    No positions yet. Create them under Field Force › Positions &amp; Vacancies.
                @elseif ($mode === 'geography')
                    No geography yet. Add Zones, Regions, Areas and HQs under Field Force › Geography.
                @else
                    No employees to show.
                @endif
            </div>
        @elseif ($display === 'chart')
            <div class="org-canvas"
                 wire:key="org-canvas-{{ $mode }}-{{ $openDepth }}-{{ $focus }}-{{ md5($search) }}"
                 x-data="{
                     zoom: 1, drag: null,
                     setZoom(z) { this.zoom = Math.min(1.5, Math.max(0.3, Math.round(z * 100) / 100)); },
                     fit() {
                         const chart = this.$refs.chart;
                         chart.style.zoom = 1;
                         const ratio = (this.$el.clientWidth - 32) / chart.scrollWidth;
                         this.setZoom(Math.min(1, ratio));
                         this.$nextTick(() => this.center());
                     },
                     center() { this.$el.scrollLeft = (this.$el.scrollWidth - this.$el.clientWidth) / 2; },
                     start(e) { if (e.button !== 0 || e.target.closest('a,button')) return; this.drag = { x: e.clientX, y: e.clientY, l: this.$el.scrollLeft, t: this.$el.scrollTop }; },
                     move(e) { if (!this.drag) return; this.$el.scrollLeft = this.drag.l - (e.clientX - this.drag.x); this.$el.scrollTop = this.drag.t - (e.clientY - this.drag.y); },
                 }"
                 x-init="$nextTick(() => center())"
                 x-on:mousedown="start($event)"
                 x-on:mousemove.window="move($event)"
                 x-on:mouseup.window="drag = null"
                 x-bind:class="{ 'dragging': drag }">
                <div class="org-zoom org-noprint">
                    <button type="button" title="Zoom out" x-on:click="setZoom(zoom - 0.1)">−</button>
                    <span x-text="Math.round(zoom * 100) + '%'"></span>
                    <button type="button" title="Zoom in" x-on:click="setZoom(zoom + 0.1)">+</button>
                    <button type="button" title="Fit to screen" x-on:click="fit()">⤢</button>
                </div>
                <div class="oc" x-ref="chart" x-bind:style="'zoom:' + zoom">
                    <ul>
                        @foreach ($tree as $node)
                            @include('filament.pages.partials.organogram-chart-node', ['node' => $node, 'depth' => 1])
                        @endforeach
                    </ul>
                </div>
            </div>
        @else
            <div class="org-panel overflow-x-auto">
                <ul class="org-tree" wire:key="org-list-{{ $mode }}-{{ $openDepth }}-{{ $focus }}">
                    @foreach ($tree as $node)
                        @include('filament.pages.partials.organogram-node', ['node' => $node, 'depth' => 1])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
