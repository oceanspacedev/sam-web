<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class DiagramJabatan extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected string $view = 'filament.pages.diagram-jabatan';

    protected static string | \UnitEnum | null $navigationGroup = 'Struktur Organisasi';

    protected static ?string $navigationLabel = 'Diagram Jabatan';

    protected static ?string $title = 'Diagram Jabatan & Hirarki Organisasi';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->role?->can_access_web === 1;
    }

    public function getSubheading(): ?string
    {
        return 'Visualisasi hirarki Badan Usaha → Division → Region → Cluster, pohon peran (parent_role_id), dan tim (tm_id) dari data live DB. Murni visualisasi — tidak mengubah skema.';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'treeData' => $this->buildTreeData(),
        ];
    }

    /** @return array<string, mixed> */
    protected function buildTreeData(): array
    {
        $badanUsahaScopeUsers = $this->scopeUsersByPivot('user_badan_usaha', 'badanusaha_id', 'badanusaha');
        $divisionScopeUsers = $this->scopeUsersByPivot('user_divisi', 'divisi_id', 'divisi');
        $regionScopeUsers = $this->scopeUsersByPivot('user_regions', 'region_id', 'region');
        $clusterScopeUsers = $this->scopeUsersByPivot('user_clusters', 'cluster_id', 'cluster');

        $clusters = DB::table('clusters')
            ->whereNull('clusters.deleted_at')
            ->select('clusters.id', 'clusters.code', 'clusters.name', 'clusters.region_id')
            ->get();

        $clusterByRegion = [];
        foreach ($clusters as $c) {
            $clusterByRegion[$c->region_id][] = [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'scope_users' => $clusterScopeUsers[$c->id] ?? [],
            ];
        }

        $regions = DB::table('regions')->whereNull('regions.deleted_at')
            ->select('id', 'code', 'name', 'divisi_id')->get();
        $regionByDiv = [];
        foreach ($regions as $r) {
            $kids = $clusterByRegion[$r->id] ?? [];
            $regionByDiv[$r->divisi_id][] = [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'cluster_count' => count($kids),
                'scope_users' => $regionScopeUsers[$r->id] ?? [],
                'children' => $kids,
            ];
        }

        $divs = DB::table('divisions')->whereNull('divisions.deleted_at')
            ->select('id', 'code', 'name', 'badanusaha_id')->get();
        $divByBU = [];
        foreach ($divs as $d) {
            $kids = $regionByDiv[$d->id] ?? [];
            $cCount = array_sum(array_map(fn ($k) => $k['cluster_count'], $kids));
            $divByBU[$d->badanusaha_id][] = [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->name,
                'region_count' => count($kids),
                'cluster_count' => $cCount,
                'scope_users' => $divisionScopeUsers[$d->id] ?? [],
                'children' => $kids,
            ];
        }

        $bus = DB::table('badan_usahas')->whereNull('badan_usahas.deleted_at')
            ->select('id', 'code', 'name')->get();
        $org = [];
        foreach ($bus as $b) {
            $kids = $divByBU[$b->id] ?? [];
            $rCount = array_sum(array_map(fn ($k) => $k['region_count'], $kids));
            $cCount = array_sum(array_map(fn ($k) => $k['cluster_count'], $kids));
            $org[] = [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'division_count' => count($kids),
                'region_count' => $rCount,
                'cluster_count' => $cCount,
                'scope_users' => $badanUsahaScopeUsers[$b->id] ?? [],
                'children' => $kids,
            ];
        }

        // Pohon peran (parent_role_id) — role aktif
        $roles = DB::table('roles')->whereNull('deleted_at')
            ->select('id', 'name', 'parent_role_id', 'organizational_scope_level', 'can_access_web')
            ->get();
        $roleById = [];
        foreach ($roles as $r) {
            $roleById[$r->id] = [
                'id' => $r->id,
                'name' => $r->name,
                'parent_role_id' => $r->parent_role_id,
                'scope_level' => $r->organizational_scope_level,
                'can_access_web' => (int) $r->can_access_web,
                'children' => [],
            ];
        }
        foreach ($roles as $r) {
            if ($r->parent_role_id && isset($roleById[$r->parent_role_id])) {
                $roleById[$r->parent_role_id]['children'][] = &$roleById[$r->id];
            }
        }
        $roleRoots = array_values(array_filter($roleById, fn ($r) => $r['parent_role_id'] === null));

        // Top team lead via tm_id (aktif), exclude self-reference
        $tmGroups = DB::table('users')
            ->whereNull('users.deleted_at')
            ->whereNotNull('users.tm_id')
            ->select('users.tm_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('users.tm_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();
        $leadIds = $tmGroups->pluck('tm_id')->unique()->values()->all();
        $leads = DB::table('users')->whereIn('id', $leadIds)->whereNull('deleted_at')
            ->select('id', 'nama_lengkap', 'role_id')->get()->keyBy('id');
        $roleNames = DB::table('roles')->pluck('name', 'id');
        $tmLeads = [];
        foreach ($tmGroups as $g) {
            $lead = $leads[$g->tm_id] ?? null;
            if (! $lead) {
                continue;
            }
            $tmLeads[] = [
                'lead_id' => $lead->id,
                'lead_name' => $lead->nama_lengkap ?? ('id#'.$lead->id),
                'role' => $roleNames[$lead->role_id] ?? '',
                'member_count' => $g->cnt - ($g->tm_id == $lead->id ? 1 : 0),
            ];
        }

        $summary = [
            'badan_usaha_total' => DB::table('badan_usahas')->count(),
            'badan_usaha_active' => DB::table('badan_usahas')->whereNull('deleted_at')->count(),
            'division_total' => DB::table('divisions')->count(),
            'division_active' => DB::table('divisions')->whereNull('deleted_at')->count(),
            'region_total' => DB::table('regions')->count(),
            'region_active' => DB::table('regions')->whereNull('deleted_at')->count(),
            'cluster_total' => DB::table('clusters')->count(),
            'cluster_active' => DB::table('clusters')->whereNull('deleted_at')->count(),
            'user_total' => DB::table('users')->count(),
            'user_active' => DB::table('users')->whereNull('deleted_at')->count(),
            'role_total' => DB::table('roles')->count(),
            'role_active' => DB::table('roles')->whereNull('deleted_at')->count(),
        ];

        return [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'summary' => $summary,
            'org' => $org,
            'roles' => $roleRoots,
            'tm_leads' => $tmLeads,
        ];
    }

    /**
     * @return array<int, array<int, array{id: int, name: string, username: string, role: string, assignment_count: int, is_multi: bool}>>
     */
    protected function scopeUsersByPivot(string $pivotTable, string $pivotColumn, string $scopeLevel): array
    {
        $assignmentCounts = DB::table($pivotTable)
            ->select('user_id', DB::raw("COUNT(DISTINCT {$pivotColumn}) as assignment_count"))
            ->groupBy('user_id');

        $rows = DB::table($pivotTable)
            ->join('users', 'users.id', '=', "{$pivotTable}.user_id")
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->joinSub($assignmentCounts, 'assignment_counts', function ($join) use ($pivotTable): void {
                $join->on('assignment_counts.user_id', '=', "{$pivotTable}.user_id");
            })
            ->whereNull('users.deleted_at')
            ->where('roles.organizational_scope_level', $scopeLevel)
            ->select(
                "{$pivotTable}.{$pivotColumn} as scope_id",
                'users.id',
                'users.username',
                'users.nama_lengkap',
                'roles.name as role_name',
                'assignment_counts.assignment_count',
            )
            ->get()
            ->sortBy([
                ['scope_id', 'asc'],
                ['nama_lengkap', 'asc'],
                ['username', 'asc'],
            ]);

        $usersByScope = [];

        foreach ($rows as $row) {
            $usersByScope[(int) $row->scope_id][] = [
                'id' => (int) $row->id,
                'name' => $row->nama_lengkap ?: ($row->username ?: 'User #'.$row->id),
                'username' => (string) ($row->username ?? ''),
                'role' => (string) ($row->role_name ?? ''),
                'assignment_count' => (int) $row->assignment_count,
                'is_multi' => (int) $row->assignment_count > 1,
            ];
        }

        return $usersByScope;
    }
}
