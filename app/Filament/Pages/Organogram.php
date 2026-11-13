<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Geography;
use App\Models\Position;
use App\Support\FieldForce;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * Organisation chart in three views:
 *  - People: the reporting-manager tree (works for every company, corporate or pharma).
 *  - Field Positions: the position/seat tree with vacancies (field-force companies only).
 *  - Geography: Zone > Region > Area > HQ > Territory with filled/vacant counts.
 *
 * HR (employee.view) sees the whole organisation; anyone else sees their own team.
 */
class Organogram extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Employees';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Organogram';

    protected static string $view = 'filament.pages.organogram';

    public string $mode = 'people';

    public ?string $companyId = null;

    public ?string $departmentId = null;

    public ?string $divisionId = null;

    public ?string $workforceType = null;

    public string $search = '';

    /** Nodes up to this depth start expanded; 0 = automatic (everything for small charts, two levels otherwise). */
    public int $expandDepth = 0;

    /** 'chart' (top-down org chart) or 'list' (indented tree, better on phones). */
    public string $display = 'chart';

    /** Node key to show on its own (with the path above it), e.g. "e12". */
    public ?string $focus = null;

    /** Charts at or below this size open fully expanded. */
    private const AUTO_EXPAND_ALL_UP_TO = 40;

    /** Stripe colours for departments / levels (cycled). */
    private const PALETTE = ['#0F7A78', '#F08A24', '#4F46E5', '#DB2777', '#059669', '#CA8A04', '#0284C7', '#7C3AED', '#DC2626', '#65A30D'];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && ($user->can('employee.view') || $user->employee_id !== null);
    }

    public function mount(): void
    {
        $requested = request()->query('mode');
        if (is_string($requested) && array_key_exists($requested, $this->availableModes())) {
            $this->mode = $requested;
        }
    }

    /** @return array<string, string> */
    public function availableModes(): array
    {
        $modes = ['people' => 'People (Reporting Lines)'];

        if ($this->canSeeFieldForce()) {
            $modes['positions'] = 'Field Positions';
            $modes['geography'] = 'Geography';
        }

        return $modes;
    }

    public function canSeeFieldForce(): bool
    {
        if (! FieldForce::enabled()) {
            return false;
        }

        $user = auth()->user();

        if ($user->can('fieldforce.view') || $user->can('fieldforce.manage') || $user->can('employee.view')) {
            return true;
        }

        // Field managers see their own part of the position tree.
        return $user->employee_id !== null && Position::query()->where('employee_id', $user->employee_id)->exists();
    }

    public function setMode(string $mode): void
    {
        if (array_key_exists($mode, $this->availableModes())) {
            $this->mode = $mode;
            $this->focus = null;
        }
    }

    public function setLayout(string $layout): void
    {
        if (in_array($layout, ['chart', 'list'], true)) {
            $this->display = $layout;
        }
    }

    public function focusOn(string $key): void
    {
        $this->focus = $key;
        $this->search = '';
    }

    public function clearFocus(): void
    {
        $this->focus = null;
    }

    public function expandAll(): void
    {
        $this->expandDepth = 99;
    }

    public function collapseAll(): void
    {
        $this->expandDepth = 1;
    }

    private function seesEverything(): bool
    {
        return auth()->user()->can('employee.view');
    }

    protected function getViewData(): array
    {
        $tree = match ($this->mode) {
            'positions' => $this->canSeeFieldForce() ? $this->positionTree() : [],
            'geography' => $this->canSeeFieldForce() ? $this->geographyTree() : [],
            default => $this->peopleTree(),
        };

        $breadcrumb = [];

        if ($this->focus !== null) {
            [$focused, $breadcrumb] = $this->findWithPath($tree, $this->focus);
            $tree = $focused ? [$focused] : $tree;
            if (! $focused) {
                $this->focus = null;
            }
        }

        if (trim($this->search) !== '') {
            $tree = $this->prune($tree, Str::lower(trim($this->search)));
        }

        $stats = $this->stats($tree);
        $openDepth = $this->expandDepth ?: ($stats['total'] <= self::AUTO_EXPAND_ALL_UP_TO ? 99 : 2);

        return [
            'tree' => $tree,
            'openDepth' => $openDepth,
            'legend' => $this->legend($tree),
            'breadcrumb' => $breadcrumb,
            'modes' => $this->availableModes(),
            'stats' => $stats,
            'fieldForce' => FieldForce::enabled(),
            'companies' => $this->seesEverything() ? Company::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'departments' => $this->seesEverything() ? Department::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'divisions' => FieldForce::enabled() ? Division::query()->orderBy('name')->pluck('name', 'id') : collect(),
            'reportingLine' => $this->reportingLine(),
            'forceOpen' => trim($this->search) !== '',
        ];
    }

    // ---- People ---------------------------------------------------------------------------

    private function peopleTree(): array
    {
        $query = Employee::query()
            ->with(['designation:id,name', 'department:id,name'])
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION, Employee::STATUS_NOTICE_PERIOD])
            ->select([
                'id', 'employee_code', 'first_name', 'middle_name', 'last_name', 'designation_id', 'department_id',
                'company_id', 'reporting_manager_id', 'status', 'profile_photo_path',
                ...(FieldForce::enabled() ? ['workforce_type', 'division_id'] : []),
            ]);

        $rootIds = null;

        if (! $this->seesEverything()) {
            $me = auth()->user()->employee;
            if (! $me) {
                return [];
            }
            $query->whereIn('id', [$me->id, ...$me->allSubordinateIds()]);
            $rootIds = [$me->id];
        } else {
            $query
                ->when($this->companyId, fn ($q) => $q->where('company_id', $this->companyId))
                ->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId));
        }

        if (FieldForce::enabled()) {
            $query
                ->when($this->workforceType === 'none', fn ($q) => $q->whereNull('workforce_type'))
                ->when(in_array($this->workforceType, ['ho', 'field'], true), fn ($q) => $q->where('workforce_type', $this->workforceType))
                ->when($this->divisionId, fn ($q) => $q->where('division_id', $this->divisionId));
        }

        $employees = $query->orderBy('first_name')->get();
        $myId = auth()->user()->employee_id;
        $canOpen = auth()->user()->can('employee.view');

        $nodes = $employees->mapWithKeys(fn (Employee $e) => [$e->id => [
            'group' => $e->department?->name,
            'groupKey' => $e->department_id,
            'isMe' => $e->id === $myId,
            'url' => $canOpen ? \App\Filament\Resources\EmployeeResource::getUrl('edit', ['record' => $e->id]) : null,
            'key' => 'e'.$e->id,
            'title' => self::name($e),
            'subtitle' => $e->designation?->name,
            'meta' => trim($e->employee_code.($e->department ? ' · '.$e->department->name : '')),
            'badge' => FieldForce::enabled() && $e->workforce_type ? (FieldForce::WORKFORCE_TYPES[$e->workforce_type] ?? null) : null,
            'badgeColor' => $e->workforce_type === 'field' ? 'success' : 'info',
            'tone' => $e->status === Employee::STATUS_NOTICE_PERIOD ? 'warning' : 'normal',
            'note' => $e->status === Employee::STATUS_NOTICE_PERIOD ? 'Notice period' : null,
            'avatar' => $e->profile_photo_path,
            'initials' => Str::upper(Str::substr($e->first_name, 0, 1).Str::substr((string) $e->last_name, 0, 1)),
            'parent' => $e->reporting_manager_id,
            'search' => Str::lower(self::name($e).' '.$e->employee_code.' '.$e->designation?->name),
        ]])->all();

        return $this->assemble($nodes, $rootIds);
    }

    /** "CEO > VP Sales > you" for people who only see their own team. */
    private function reportingLine(): array
    {
        if ($this->mode !== 'people' || $this->seesEverything()) {
            return [];
        }

        $line = [];
        $current = auth()->user()->employee?->reportingManager;
        $seen = [];

        while ($current && ! in_array($current->id, $seen, true) && count($line) < 15) {
            $seen[] = $current->id;
            array_unshift($line, self::name($current).($current->designation ? ' ('.$current->designation->name.')' : ''));
            $current = $current->reportingManager;
        }

        return $line;
    }

    // ---- Positions ------------------------------------------------------------------------

    private function positionTree(): array
    {
        $query = Position::query()
            ->with(['hierarchyLevel:id,short_name,name,rank', 'geography:id,name', 'division:id,name', 'employee:id,employee_code,first_name,middle_name,last_name,status,profile_photo_path'])
            ->where('is_active', true)
            ->when($this->divisionId, fn ($q) => $q->where(fn ($w) => $w->where('division_id', $this->divisionId)->orWhereNull('division_id')))
            ->when($this->companyId && $this->seesEverything(), fn ($q) => $q->where('company_id', $this->companyId));

        $rootIds = null;
        $user = auth()->user();

        if (! ($user->can('fieldforce.view') || $user->can('fieldforce.manage') || $this->seesEverything())) {
            $mine = Position::query()->where('employee_id', $user->employee_id)->get();
            $rootIds = $mine->pluck('id')->all();
            $visible = $mine->flatMap(fn (Position $p) => $p->descendantAndSelfIds())->unique()->all();
            $query->whereIn('id', $visible);
        }

        $positions = $query->get();

        $nodes = $positions->mapWithKeys(function (Position $p) {
            $emp = $p->employee;

            return [$p->id => [
                'group' => $p->hierarchyLevel?->name,
                'groupKey' => $p->hierarchyLevel?->rank,
                'isMe' => $emp !== null && $emp->id === auth()->user()->employee_id,
                'url' => null,
                'key' => 'p'.$p->id,
                'title' => $emp ? self::name($emp) : 'VACANT',
                'subtitle' => $p->title,
                'meta' => trim($p->code.($p->geography ? ' · '.$p->geography->name : '').($p->division ? ' · '.$p->division->name : '')),
                'badge' => $p->hierarchyLevel?->short_name,
                'badgeColor' => 'primary',
                'tone' => $emp ? ($emp->status === Employee::STATUS_NOTICE_PERIOD ? 'warning' : 'normal') : 'danger',
                'note' => $emp
                    ? ($emp->status === Employee::STATUS_NOTICE_PERIOD ? 'Notice period' : null)
                    : ($p->vacant_since ? 'Vacant '.$p->vacant_since->diffInDays(now()->startOfDay()).' days' : 'Vacant'),
                'avatar' => $emp?->profile_photo_path,
                'initials' => $emp ? Str::upper(Str::substr($emp->first_name, 0, 1).Str::substr((string) $emp->last_name, 0, 1)) : '—',
                'parent' => $p->parent_position_id,
                'vacant' => $emp === null,
                'rank' => $p->hierarchyLevel?->rank ?? 99,
                'search' => Str::lower((($emp ? self::name($emp) : null) ?? 'vacant').' '.$emp?->employee_code.' '.$p->code.' '.$p->title.' '.$p->geography?->name),
            ]];
        })->all();

        return $this->assemble($nodes, $rootIds);
    }

    // ---- Geography ------------------------------------------------------------------------

    private function geographyTree(): array
    {
        $geos = Geography::query()->with('level:id,name,depth')->where('is_active', true)->get();

        $positionCounts = Position::query()
            ->where('is_active', true)
            ->whereNotNull('geography_id')
            ->when($this->divisionId, fn ($q) => $q->where('division_id', $this->divisionId))
            ->selectRaw('geography_id, COUNT(*) as total, SUM(CASE WHEN employee_id IS NULL THEN 1 ELSE 0 END) as vacant')
            ->groupBy('geography_id')
            ->get()
            ->keyBy('geography_id');

        $nodes = $geos->mapWithKeys(function (Geography $g) use ($positionCounts) {
            $c = $positionCounts->get($g->id);
            $total = (int) ($c->total ?? 0);
            $vacant = (int) ($c->vacant ?? 0);

            return [$g->id => [
                'group' => $g->level?->name,
                'groupKey' => $g->level?->depth,
                'isMe' => false,
                'url' => null,
                'key' => 'g'.$g->id,
                'title' => $g->name,
                'subtitle' => collect([$g->city, $g->state])->filter()->implode(', ') ?: null,
                'meta' => $g->code.($g->station_type ? ' · '.(Geography::STATION_TYPES[$g->station_type] ?? $g->station_type) : ''),
                'badge' => $g->level?->name,
                'badgeColor' => 'gray',
                'tone' => $vacant > 0 ? 'warning' : 'normal',
                'note' => $total > 0 ? ($total - $vacant).'/'.$total.' positions filled' : null,
                'avatar' => null,
                'initials' => Str::upper(Str::substr($g->name, 0, 2)),
                'parent' => $g->parent_id,
                'rank' => $g->level?->depth ?? 99,
                'search' => Str::lower($g->name.' '.$g->code.' '.$g->city.' '.$g->state),
            ]];
        })->all();

        return $this->assemble($nodes);
    }

    private static function name(Employee $e): string
    {
        return (string) preg_replace("/\s+/", ' ', $e->full_name);
    }

    // ---- Tree helpers ---------------------------------------------------------------------

    /**
     * Turns a flat [id => node] map with 'parent' ids into nested nodes. Anything whose
     * parent isn't in the set becomes a root (or, when $rootIds is given, only those are roots).
     */
    private function assemble(array $nodes, ?array $rootIds = null): array
    {
        $childrenOf = [];
        foreach ($nodes as $id => $node) {
            $parent = $node['parent'];
            $isRoot = $rootIds !== null ? in_array($id, $rootIds, true) : ($parent === null || ! isset($nodes[$parent]) || $parent === $id);
            $childrenOf[$isRoot ? 0 : $parent][] = $id;
        }

        $visited = [];
        $build = function (int $id) use (&$build, &$nodes, &$childrenOf, &$visited): ?array {
            if (isset($visited[$id])) {
                return null; // guard against a bad loop in data
            }
            $visited[$id] = true;

            $node = $nodes[$id];
            $children = [];
            foreach ($childrenOf[$id] ?? [] as $childId) {
                if ($child = $build($childId)) {
                    $children[] = $child;
                }
            }

            usort($children, fn ($a, $b) => [($a['rank'] ?? 0), $a['title']] <=> [($b['rank'] ?? 0), $b['title']]);
            $node['children'] = $children;
            $node['direct'] = count($children);
            // Reports who have nobody under them stack in a column, which keeps wide teams readable.
            $node['stackChildren'] = count($children) > 2 && collect($children)->every(fn ($c) => $c['children'] === []);
            $node['color'] = $node['groupKey'] !== null ? self::PALETTE[abs(crc32((string) $node['groupKey'])) % count(self::PALETTE)] : '#9CA3AF';
            $node['descendants'] = array_sum(array_map(fn ($c) => 1 + $c['descendants'], $children));
            $node['vacancies'] = (($node['vacant'] ?? false) ? 1 : 0) + array_sum(array_map(fn ($c) => $c['vacancies'], $children));
            unset($node['parent']);

            return $node;
        };

        $roots = [];
        foreach ($childrenOf[0] ?? [] as $rootId) {
            if ($root = $build($rootId)) {
                $roots[] = $root;
            }
        }

        usort($roots, fn ($a, $b) => [$b['descendants'], $a['title']] <=> [$a['descendants'], $b['title']]);

        return $roots;
    }

    /** Keeps nodes matching the search, plus the path down to them. */
    private function prune(array $nodes, string $needle): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $children = $this->prune($node['children'], $needle);

            if ($children !== [] || str_contains($node['search'], $needle)) {
                $node['children'] = str_contains($node['search'], $needle) && $children === [] ? $node['children'] : $children;
                $node['match'] = str_contains($node['search'], $needle);
                $result[] = $node;
            }
        }

        return $result;
    }

    /**
     * Find a node by key; returns [node|null, ancestors as [key, title] pairs from the top].
     *
     * @return array{0: ?array, 1: array<int, array{key: string, title: string}>}
     */
    private function findWithPath(array $nodes, string $key, array $path = []): array
    {
        foreach ($nodes as $node) {
            if ($node['key'] === $key) {
                return [$node, $path];
            }

            [$found, $foundPath] = $this->findWithPath($node['children'], $key, [...$path, ['key' => $node['key'], 'title' => $node['title']]]);

            if ($found) {
                return [$found, $foundPath];
            }
        }

        return [null, []];
    }

    /** Colour legend: group label => colour, for the groups present in the tree. */
    private function legend(array $nodes, array $legend = []): array
    {
        foreach ($nodes as $node) {
            if (filled($node['group'] ?? null)) {
                $legend[$node['group']] = $node['color'];
            }
            $legend = $this->legend($node['children'], $legend);
        }

        ksort($legend);

        return $legend;
    }

    private function stats(array $tree): array
    {
        $total = array_sum(array_map(fn ($n) => 1 + $n['descendants'], $tree));
        $vacant = array_sum(array_map(fn ($n) => $n['vacancies'], $tree));

        return ['total' => $total, 'vacant' => $vacant, 'roots' => count($tree)];
    }
}
