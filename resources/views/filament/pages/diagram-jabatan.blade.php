<x-filament-panels::page>
    @php
        $s = $treeData['summary'];
        $generatedAt = $treeData['generated_at'] ?? null;

        $cardWrap = 'p-1 bg-gray-50 dark:bg-gray-950 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden';
        $cardInner = 'rounded-lg bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10';
        $sectionHead = 'font-sans text-base font-semibold text-gray-900 dark:text-white';
        $sectionDesc = 'mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400';

        $tiles = [
            ['Badan Usaha', $s['badan_usaha_active'], $s['badan_usaha_total'], 'heroicon-o-building-office-2', 'bg-indigo-500'],
            ['Division',    $s['division_active'],    $s['division_total'],    'heroicon-o-rectangle-stack', 'bg-sky-500'],
            ['Region',      $s['region_active'],      $s['region_total'],      'heroicon-o-map',             'bg-teal-500'],
            ['Cluster',     $s['cluster_active'],     $s['cluster_total'],     'heroicon-o-squares-2x2',     'bg-amber-500'],
            ['User',        $s['user_active'],        $s['user_total'],        'heroicon-o-users',          'bg-emerald-500'],
            ['Role',        $s['role_active'],        $s['role_total'],        'heroicon-o-shield-check',   'bg-violet-500'],
        ];

        $tabBtn = 'rounded-md px-3 py-1.5 text-sm font-medium transition-colors whitespace-nowrap';
        $tabActive = 'bg-primary-600 text-white shadow-sm';
        $tabIdle = 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white';
    @endphp

    <div wire:ignore x-data="diagramJabatanChart(@js($treeData))" x-init="init()" x-on:keydown.escape.window="isCanvasFullscreen && toggleCanvasFullscreen()" class="space-y-8">

        {{-- Meta row --}}
        @if ($generatedAt)
            <p class="text-xs text-gray-400 dark:text-gray-500">
                Data live dari database · diperbarui {{ $generatedAt }}
            </p>
        @endif

        {{-- Stat cards (dashboard pattern) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($tiles as $t)
                <div class="{{ $cardWrap }}">
                    <div class="{{ $cardInner }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $t[0] }}</span>
                            <span class="size-2 rounded-full {{ $t[4] }}"></span>
                        </div>
                        <p class="mt-2 font-sans text-2xl font-bold text-gray-900 dark:text-white">{{ $t[1] }}</p>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $t[2] }} total · {{ max($t[2] - $t[1], 0) }} nonaktif</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab switcher (dashboard segmented control) --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex max-w-full overflow-x-auto rounded-lg border border-gray-200 bg-white p-0.5 dark:border-white/10 dark:bg-gray-900" role="tablist" aria-label="Tampilan diagram">
                <button type="button" role="tab" x-on:click="pane = 'org'" :aria-selected="pane === 'org'" :class="pane === 'org' ? '{{ $tabBtn }} {{ $tabActive }}' : '{{ $tabBtn }} {{ $tabIdle }}'">
                    Hirarki Organisasi
                </button>
                <button type="button" role="tab" x-on:click="pane = 'roles'" :aria-selected="pane === 'roles'" :class="pane === 'roles' ? '{{ $tabBtn }} {{ $tabActive }}' : '{{ $tabBtn }} {{ $tabIdle }}'">
                    Pohon Peran
                </button>
                <button type="button" role="tab" x-on:click="pane = 'tm'" :aria-selected="pane === 'tm'" :class="pane === 'tm' ? '{{ $tabBtn }} {{ $tabActive }}' : '{{ $tabBtn }} {{ $tabIdle }}'">
                    Hirarki Tim
                </button>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500" x-show="pane === 'org'" x-cloak>Drag area kosong untuk geser · scroll untuk zoom</p>
            <p class="text-xs text-gray-400 dark:text-gray-500" x-show="pane === 'roles'" x-cloak>Klik kotak untuk expand/collapse cabang peran</p>
            <p class="text-xs text-gray-400 dark:text-gray-500" x-show="pane === 'tm'" x-cloak>Diurutkan berdasarkan jumlah anggota tim</p>
        </div>

        {{-- ORG canvas --}}
        <div x-show="pane === 'org'" role="tabpanel" class="dj-panel">
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Hirarki Organisasi</h3>
                    <p class="{{ $sectionDesc }}">Badan Usaha → Division → Region → Cluster. Klik chip <span class="font-medium text-gray-600 dark:text-gray-300">User</span> untuk melihat daftar user akses per level.</p>
                </div>
                <div class="{{ $cardInner }} !p-0 overflow-hidden">
                    <div
                        x-ref="orgViewport"
                        class="orgchart-canvas"
                        x-bind:class="{ 'is-fullscreen': isCanvasFullscreen }"
                        x-on:pointerdown="startPan($event)"
                        x-on:pointermove.window="panCanvas($event)"
                        x-on:pointerup.window="stopPan()"
                        x-on:pointercancel.window="stopPan()"
                        x-on:wheel.prevent="zoomWheel($event)"
                    >
                        <div class="dj-float-controls" x-on:pointerdown.stop x-on:click.stop x-on:wheel.stop>
                            <div class="dj-float-controls-left">
                                <div class="dj-cluster">
                                    <x-filament::icon icon="heroicon-o-funnel" class="dj-cluster-icon" />
                                    <select x-model="selectedBadanUsaha" x-on:change="renderOrgTree()" class="dj-select" aria-label="Filter Badan Usaha">
                                        <option value="all">Semua Badan Usaha</option>
                                        <template x-for="item in orgOptions" :key="item.id">
                                            <option :value="String(item.id)" x-text="item.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                            <div class="dj-float-controls-right">
                                <div class="dj-cluster" role="group" aria-label="Zoom">
                                    <button type="button" x-on:click="zoomOut()" class="dj-icon-btn" title="Perkecil"><x-filament::icon icon="heroicon-o-minus" class="size-4" /></button>
                                    <button type="button" x-on:click="resetZoom()" class="dj-zoom-label" title="Reset zoom" x-text="`${Math.round(orgZoom * 100)}%`"></button>
                                    <button type="button" x-on:click="zoomIn()" class="dj-icon-btn" title="Perbesar"><x-filament::icon icon="heroicon-o-plus" class="size-4" /></button>
                                </div>
                                <div class="dj-cluster" role="group" aria-label="Expand collapse">
                                    <button type="button" x-on:click="expandAll('org', true)" class="dj-icon-btn" title="Expand semua"><x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-4" /></button>
                                    <button type="button" x-on:click="expandAll('org', false)" class="dj-icon-btn" title="Collapse semua"><x-filament::icon icon="heroicon-o-arrows-pointing-in" class="size-4" /></button>
                                </div>
                                <button type="button" x-on:click="toggleCanvasFullscreen()" class="dj-cluster dj-cluster--action" :title="isCanvasFullscreen ? 'Keluar fullscreen' : 'Fullscreen'">
                                    <x-filament::icon icon="heroicon-o-window" class="size-4" x-show="!isCanvasFullscreen" />
                                    <x-filament::icon icon="heroicon-o-x-mark" class="size-4" x-show="isCanvasFullscreen" x-cloak />
                                </button>
                            </div>
                        </div>

                        <div class="dj-legend" x-on:pointerdown.stop x-on:click.stop x-on:wheel.stop>
                            <span class="dj-legend-item"><span class="dj-dot bg-indigo-500"></span>BU</span>
                            <span class="dj-legend-item"><span class="dj-dot bg-sky-500"></span>Div</span>
                            <span class="dj-legend-item"><span class="dj-dot bg-teal-500"></span>Reg</span>
                            <span class="dj-legend-item"><span class="dj-dot bg-amber-500"></span>Clu</span>
                        </div>

                        <ul x-ref="orgTree" class="orgchart !list-none !m-0 !p-0"></ul>

                        <div
                            x-show="hoveredNodeUsers"
                            x-cloak
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="dj-user-panel overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-900"
                            x-on:pointerdown.stop x-on:click.stop x-on:wheel.stop
                        >
                            <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="formatPersonName(hoveredNodeUsers?.title)"></p>
                                        <span class="inline-flex shrink-0 items-center rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30" x-text="hoveredNodeUsers?.areaLabel"></span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" x-show="!hoveredNodeUsers?.loading && hoveredNodeUsers?.users?.length" x-text="`${hoveredNodeUsers?.users?.length || 0} user dengan akses`"></p>
                                </div>
                                <button type="button" x-on:click="closeUserPopover()" aria-label="Tutup" class="dj-icon-btn dj-icon-btn--muted">
                                    <x-filament::icon icon="heroicon-o-x-mark" class="size-4" />
                                </button>
                            </div>

                            <div class="max-h-72 overflow-y-auto">
                                <template x-if="hoveredNodeUsers?.loading">
                                    <div class="flex items-center justify-center gap-2 px-4 py-8 text-sm text-gray-500 dark:text-gray-400">
                                        <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        Memuat daftar user...
                                    </div>
                                </template>

                                <template x-if="hoveredNodeUsers && !hoveredNodeUsers.loading && hoveredNodeUsers.error">
                                    <div class="flex flex-col items-center px-6 py-8 text-center">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="size-6 text-amber-500" />
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Gagal memuat daftar user.</p>
                                    </div>
                                </template>

                                <template x-if="hoveredNodeUsers && !hoveredNodeUsers.loading && !hoveredNodeUsers.error && hoveredNodeUsers.users.length === 0">
                                    <div class="flex flex-col items-center px-6 py-10 text-center">
                                        <x-filament::icon icon="heroicon-o-user-minus" class="size-6 text-gray-300 dark:text-gray-600" />
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada user aktif di level ini.</p>
                                    </div>
                                </template>

                                <ul class="divide-y divide-gray-200 dark:divide-white/10" x-show="hoveredNodeUsers && !hoveredNodeUsers.loading && !hoveredNodeUsers.error && hoveredNodeUsers.users.length > 0">
                                    <template x-for="user in hoveredNodeUsers?.users || []" :key="user.id">
                                        <li class="flex items-start gap-3 px-4 py-3.5">
                                            <span
                                                class="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[10px] font-semibold text-gray-600 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10"
                                                x-text="userInitials(user.name)"
                                            ></span>
                                            <div class="min-w-0 flex-1 space-y-1.5">
                                                <div>
                                                    <p class="text-sm font-medium leading-snug text-gray-900 dark:text-white" x-text="formatPersonName(user.name)"></p>
                                                    <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                                        <span x-text="user.role"></span>
                                                        <template x-if="user.username"><span x-text="` · @${user.username}`"></span></template>
                                                        <template x-if="shouldShowCoverage(user)">
                                                            <span class="text-gray-400 dark:text-gray-500" x-text="` · ${user.areas.length} wilayah`"></span>
                                                        </template>
                                                    </p>
                                                </div>

                                                <template x-if="shouldShowCoverage(user) && !isUserAreasExpanded(user.id)">
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Wilayah lain</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <template x-for="area in otherAreas(user).slice(0, 3)" :key="`${user.id}-preview-${area.id}`">
                                                                <span class="inline-flex rounded-md bg-gray-50 px-2 py-1 text-[11px] leading-none text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10" x-text="formatArea(area)"></span>
                                                            </template>
                                                            <span
                                                                x-show="otherAreas(user).length > 3"
                                                                class="inline-flex rounded-md bg-gray-50 px-2 py-1 text-[11px] leading-none text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10"
                                                                x-text="`+${otherAreas(user).length - 3} lainnya`"
                                                            ></span>
                                                        </div>
                                                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" x-on:click="toggleUserAreas(user.id)">Lihat semua</button>
                                                    </div>
                                                </template>

                                                <template x-if="shouldShowCoverage(user) && isUserAreasExpanded(user.id)">
                                                    <div class="space-y-1.5">
                                                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Semua wilayah</p>
                                                        <div class="flex flex-wrap gap-1.5">
                                                            <template x-for="area in sortedAreas(user)" :key="`${user.id}-${area.id}`">
                                                                <span
                                                                    class="inline-flex rounded-md px-2 py-1 text-[11px] leading-none ring-1"
                                                                    :class="area.id === hoveredNodeUsers?.scopeId
                                                                        ? 'bg-primary-50 font-semibold text-primary-700 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30'
                                                                        : 'bg-gray-50 text-gray-600 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10'"
                                                                    x-text="formatArea(area)"
                                                                ></span>
                                                            </template>
                                                        </div>
                                                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" x-on:click="toggleUserAreas(user.id)">Tutup</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </li>
                                    </template>
                            </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ROLES tree --}}
        <div x-show="pane === 'roles'" x-cloak role="tabpanel" class="dj-panel">
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Pohon Peran</h3>
                    <p class="{{ $sectionDesc }}">Struktur <code class="font-mono text-xs">parent_role_id</code> menunjukkan lineage pelaporan antar role, bukan filter akses data operasional.</p>
                </div>
                <div class="{{ $cardInner }} !p-0 overflow-hidden">
                    <div class="orgchart-scroll">
                        <div class="dj-float-controls dj-float-controls--compact" x-on:pointerdown.stop x-on:click.stop x-on:wheel.stop>
                            <div class="dj-cluster" role="group" aria-label="Expand collapse">
                                <button type="button" x-on:click="expandAll('role', true)" class="dj-icon-btn" title="Expand semua"><x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-4" /></button>
                                <button type="button" x-on:click="expandAll('role', false)" class="dj-icon-btn" title="Collapse semua"><x-filament::icon icon="heroicon-o-arrows-pointing-in" class="size-4" /></button>
                            </div>
                        </div>
                        <ul x-ref="roleTree" class="orgchart !list-none !m-0 !p-0"></ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- TM cards --}}
        <div x-show="pane === 'tm'" x-cloak role="tabpanel" class="dj-panel">
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Hirarki Tim</h3>
                    <p class="{{ $sectionDesc }}">Top team lead via <code class="font-mono text-xs">tm_id</code> · jumlah anggota mengecualikan baris self-referencing.</p>
                </div>
                <div class="{{ $cardInner }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($treeData['tm_leads'] as $i => $lead)
                            <div class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200 transition hover:ring-gray-300 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-white/20">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">{{ $i + 1 }}</span>
                                    <span class="inline-flex size-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                        <x-filament::icon icon="heroicon-o-user-group" class="size-4" />
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <p class="font-sans text-lg font-semibold text-gray-900 dark:text-white">{{ $lead['lead_name'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $lead['role'] }}</p>
                                </div>
                                <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                    <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                                        <dt class="text-xs text-gray-500 dark:text-gray-400">Anggota</dt>
                                        <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $lead['member_count'] }}</dd>
                                    </div>
                                    <div class="rounded-md bg-white px-2.5 py-2 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                                        <dt class="text-xs text-gray-500 dark:text-gray-400">Lead ID</dt>
                                        <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">#{{ $lead['lead_id'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                        <span class="font-medium text-gray-700 dark:text-gray-200">Catatan:</span>
                        <code class="font-mono text-xs">tm_id</code> dipakai untuk supervision/approval &amp; grouping,
                        <span class="font-medium text-gray-700 dark:text-gray-200">bukan</span> batas akses data.
                        Data anggota tim tetap ditentukan oleh role dan area kerja masing-masing.
                    </div>
                </div>
            </div>
        </div>

    </div>

    <style>
        [x-cloak]{display:none!important}
        .dj-panel{display:grid;gap:1rem}

        /* Floating control clusters (dashboard segmented style) */
        .dj-float-controls{position:absolute;top:12px;left:12px;right:12px;z-index:45;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;pointer-events:none}
        .dj-float-controls--compact{justify-content:flex-start}
        .dj-float-controls-left,.dj-float-controls-right{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;pointer-events:auto}
        .dj-cluster{display:inline-flex;align-items:center;gap:.125rem;border-radius:.5rem;border:1px solid rgb(229 231 235);background:#fff;padding:.125rem;box-shadow:0 1px 3px rgba(15,23,42,.06)}
        .dark .dj-cluster{border-color:rgba(255,255,255,.1);background:rgb(17 24 39);box-shadow:0 2px 8px rgba(0,0,0,.25)}
        .dj-cluster--action{padding:.375rem;color:#374151;transition:background-color .12s,color .12s}
        .dj-cluster--action:hover{background:#f9fafb;color:#111827}
        .dark .dj-cluster--action{color:#d1d5db}
        .dark .dj-cluster--action:hover{background:rgba(255,255,255,.08);color:#fff}
        .dj-cluster-icon{margin-left:.5rem;width:1rem;height:1rem;color:#9ca3af;flex-shrink:0}
        .dj-select{max-width:11rem;border:0;background:transparent;padding:.375rem .5rem .375rem .25rem;font-size:.8125rem;font-weight:500;color:#374151;outline:none;cursor:pointer}
        .dark .dj-select{color:#e5e7eb}
        .dj-icon-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:.375rem;border:0;background:transparent;padding:.375rem;color:#6b7280;transition:background-color .12s,color .12s}
        .dj-icon-btn:hover{background:#f3f4f6;color:#111827}
        .dark .dj-icon-btn{color:#9ca3af}
        .dark .dj-icon-btn:hover{background:rgba(255,255,255,.08);color:#fff}
        .dj-icon-btn--muted{padding:.25rem}
        .dj-zoom-label{min-width:2.75rem;border:0;background:transparent;padding:.375rem .25rem;font-size:.75rem;font-weight:600;font-variant-numeric:tabular-nums;color:#6b7280;cursor:pointer;border-radius:.375rem;transition:background-color .12s,color .12s}
        .dj-zoom-label:hover{background:#f3f4f6;color:#111827}
        .dark .dj-zoom-label{color:#9ca3af}
        .dark .dj-zoom-label:hover{background:rgba(255,255,255,.08);color:#fff}

        .dj-legend{position:absolute;bottom:12px;left:12px;z-index:45;display:inline-flex;align-items:center;gap:.625rem;border-radius:.5rem;border:1px solid rgb(229 231 235);background:rgba(255,255,255,.92);padding:.25rem .5rem;font-size:.6875rem;color:#6b7280;backdrop-filter:blur(6px);pointer-events:auto}
        .dark .dj-legend{border-color:rgba(255,255,255,.1);background:rgba(17,24,39,.9);color:#9ca3af}
        .dj-legend-item{display:inline-flex;align-items:center;gap:.375rem}
        .dj-dot{width:.5rem;height:.5rem;border-radius:9999px;flex-shrink:0}

        /* User access panel */
        .dj-user-panel{position:absolute;z-index:50;bottom:12px;right:12px;width:min(380px,calc(100% - 24px));max-height:min(420px,calc(100% - 80px));display:flex;flex-direction:column;pointer-events:auto}

        .orgchart-canvas{overflow:hidden;position:relative;height:min(72vh,760px);cursor:grab;touch-action:none;user-select:none;background:rgb(249 250 251)}
        .orgchart-canvas.is-panning{cursor:grabbing}
        .orgchart-canvas.is-fullscreen{position:fixed;inset:16px;z-index:60;height:auto;border-radius:12px;background:#f8fafc;box-shadow:0 24px 80px rgba(15,23,42,.28)}
        .dark .orgchart-canvas{background:rgb(3 7 18)}
        .dark .orgchart-canvas.is-fullscreen{background:#020617;box-shadow:0 24px 80px rgba(0,0,0,.65)}
        .orgchart-scroll{overflow:auto;position:relative;padding:52px 20px 28px;max-height:min(72vh,760px);background:rgb(249 250 251)}
        .dark .orgchart-scroll{background:rgb(3 7 18)}

        .orgchart,.orgchart ul{list-style:none;margin:0;padding:0;display:flex;justify-content:center;position:relative}
        .orgchart{width:max-content;min-width:100%;justify-content:flex-start;transform-origin:top left;transition:transform .18s cubic-bezier(.2,.8,.2,1);--org-line:rgb(203 213 225)}
        .orgchart-canvas .orgchart{position:absolute;top:0;left:0;will-change:transform}
        .orgchart-canvas.is-panning .orgchart{transition:none}
        .dark .orgchart{--org-line:rgba(255,255,255,.16)}
        .orgchart ul{padding-top:26px;flex-wrap:nowrap}
        .orgchart li{position:relative;padding:26px 14px 0;display:flex;flex-direction:column;align-items:center}
        .orgchart>li{padding-top:0}
        .orgchart li::before,.orgchart li::after{content:'';position:absolute;top:0;right:50%;border-top:2px solid var(--org-line);width:50%;height:26px}
        .orgchart li::after{right:auto;left:50%;border-left:2px solid var(--org-line)}
        .orgchart li:only-child::before{display:none}
        .orgchart li:only-child::after{border-top:0;width:0;height:26px;right:auto;left:50%;border-left:2px solid var(--org-line)}
        .orgchart>li::before,.orgchart>li::after{display:none}
        .orgchart li:first-child::before{border:0}
        .orgchart li:last-child::after{border:0}
        .orgchart li:last-child::before{border-right:2px solid var(--org-line);border-radius:0 6px 0 0}
        .orgchart li:first-child::after{border-radius:6px 0 0 0}
        .orgchart ul::before{content:'';position:absolute;top:0;left:50%;border-left:2px solid var(--org-line);width:0;height:26px}

        .orgcard{min-width:180px;max-width:240px;padding:11px 14px;border-radius:10px;text-align:center;cursor:pointer;transition:background-color .12s,transform .12s,border-color .12s;position:relative}
        .orgcard:hover{background-color:rgba(99,102,241,.05)}
        .dark .orgcard:hover{background-color:rgba(99,102,241,.12)}
        .orgcard .toggle{position:absolute;top:5px;right:7px;width:0;height:0;border-left:5px solid transparent;border-right:5px solid transparent;border-top:6px solid rgb(156 163 175);transition:transform .15s}
        .dark .orgcard .toggle{border-top-color:rgb(107 114 128)}
        .orgcard.is-collapsed .toggle{transform:rotate(-90deg)}
        .orgchart li.is-collapsed > ul{display:none}
        .orgcard .oname{font-size:13px;font-weight:600;max-width:196px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgb(17 24 39)}
        .dark .orgcard .oname{color:#fff}
        .orgcard .ocode{font-size:10px;font-family:ui-monospace,SFMono-Regular,monospace;margin-top:2px;color:rgb(107 114 128)}
        .dark .orgcard .ocode{color:rgb(156 163 175)}
        .orgcard .obadges{display:flex;flex-wrap:wrap;justify-content:center;gap:4px;margin-top:6px}
        .orgcard .ochip{font-size:10px;line-height:1;padding:2px 7px;border-radius:9999px;white-space:nowrap;color:rgb(75 85 99);background:rgb(243 244 246);border:1px solid rgb(229 231 235)}
        .dark .orgcard .ochip{color:rgb(156 163 175);background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.1)}
        .orgcard .ochip b{color:rgb(17 24 39);font-weight:600}
        .dark .orgcard .ochip b{color:rgb(229 231 235)}
        .orgcard .ochip-action{cursor:pointer;transition:background-color .12s,border-color .12s,color .12s,box-shadow .12s}
        .orgcard .ochip-action:hover,.orgcard .ochip-action:focus-visible,.orgcard .ochip-action.is-active{border-color:rgb(99 102 241);background:rgb(238 242 255);color:rgb(67 56 202);box-shadow:0 0 0 2px rgba(99,102,241,.14);outline:none}
        .dark .orgcard .ochip-action:hover,.dark .orgcard .ochip-action:focus-visible,.dark .orgcard .ochip-action.is-active{border-color:rgb(129 140 248);background:rgba(99,102,241,.2);color:rgb(199 210 254)}
        .orgcard .ochip-action:hover b,.orgcard .ochip-action:focus-visible b,.orgcard .ochip-action.is-active b{color:rgb(49 46 129)}
        .dark .orgcard .ochip-action:hover b,.dark .orgcard .ochip-action:focus-visible b,.dark .orgcard .ochip-action.is-active b{color:rgb(224 231 255)}


        @media (max-width:720px){
            .orgchart-canvas{height:min(65vh,560px)}
            .orgchart-canvas.is-fullscreen{inset:8px;border-radius:10px}
            .orgchart-scroll{max-height:min(65vh,560px)}
            .orgcard{min-width:160px}
            .dj-float-controls{justify-content:flex-start}
            .dj-select{max-width:8.5rem}
            .dj-user-panel{bottom:12px;left:12px;right:12px;width:auto;max-height:min(360px,50vh)}
        }
    </style>

    <script>
        window.diagramJabatanChart = function (data) {
            return {
                data: data,
                pane: 'org',
                selectedBadanUsaha: 'all',
                orgZoom: 1,
                orgPanX: 0,
                orgPanY: 0,
                panState: null,
                suppressNextCardClick: false,
                isCanvasFullscreen: false,
                orgRenderer: null,
                hoveredNodeUsers: null,
                expandedUserAreas: {},
                userCache: {},
                userFetchToken: 0,
                orgOptions: (data.org || []).map(item => ({
                    id: String(item.id),
                    name: item.name || item.code || `Badan Usaha #${item.id}`,
                })),
                init() {
                    this.orgZoom = 1;
                    this.orgPanX = 0;
                    this.orgPanY = 0;
                    this.panState = null;
                    this.suppressNextCardClick = false;
                    this.$refs.orgTree.replaceChildren();
                    this.$refs.roleTree.replaceChildren();

                    const esc = (v) => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
                    const chip = (label, val, extra) => `<span class="ochip ${extra || ''}">${label} <b>${val}</b></span>`;
                    const levelLabels = { bu: 'Badan Usaha', div: 'Division', reg: 'Region', clu: 'Cluster' };
                    const userChip = (node, level) => {
                        const count = node.user_count || 0;
                        if (!count) return '';
                        return `<button type="button" class="ochip ochip-action ochip-user" data-node-id="${esc(String(node.id))}" data-level="${esc(level)}" data-node-name="${esc(node.name || '-')}" data-node-code="${esc(node.code || '')}" data-area-label="${esc(levelLabels[level] || 'area')}" aria-label="Lihat daftar user ${esc(node.name || '-')}">User <b>${count}</b></button>`;
                    };

                    const lvlRing = {
                        bu: 'ring-1 ring-indigo-300 dark:ring-indigo-500/40',
                        div: 'ring-1 ring-sky-300 dark:ring-sky-500/40',
                        reg: 'ring-1 ring-teal-300 dark:ring-teal-500/40',
                        clu: 'ring-1 ring-amber-300 dark:ring-amber-500/40',
                        role: 'ring-1 ring-violet-300 dark:ring-violet-500/40',
                    };
                    const nextLevel = { bu: 'div', div: 'reg', reg: 'clu' };
                    const cardBase = 'orgcard bg-white dark:bg-gray-900';

                    const renderOrg = (node, level, parentEl, depth) => {
                        const li = document.createElement('li');
                        const hasKids = !!(node.children && node.children.length);
                        const expanded = true;
                        const badges = {
                            bu: chip('Div', node.division_count || 0) + chip('Reg', node.region_count || 0) + chip('Clu', node.cluster_count || 0) + userChip(node, level),
                            div: chip('Reg', node.region_count || 0) + chip('Clu', node.cluster_count || 0) + userChip(node, level),
                            reg: chip('Clu', node.cluster_count || 0) + userChip(node, level),
                            clu: userChip(node, level),
                        }[level];
                        li.innerHTML = `<div class="${cardBase} ${lvlRing[level]}">` +
                            (hasKids ? `<span class="toggle"></span>` : '') +
                            `<div class="oname">${esc(node.name || '-')}</div>` +
                            (node.code ? `<div class="ocode">${esc(node.code)}</div>` : '') +
                            `<div class="obadges">${badges}</div></div>`;
                        if (hasKids) {
                            const ul = document.createElement('ul');
                            ul.style.display = expanded ? 'flex' : 'none';
                            if (!expanded) li.querySelector('.orgcard').classList.add('is-collapsed');
                            node.children.forEach(c => renderOrg(c, nextLevel[level], ul, depth + 1));
                            li.appendChild(ul);
                        }
                        parentEl.appendChild(li);
                    };

                    this.orgRenderer = renderOrg;

                    const scopeCls = { all: 'text-emerald-600 dark:text-emerald-400', badanusaha: 'text-indigo-600 dark:text-indigo-400', divisi: 'text-sky-600 dark:text-sky-400', region: 'text-teal-600 dark:text-teal-400', cluster: 'text-amber-600 dark:text-amber-400' };
                    const renderRole = (node, parentEl, depth) => {
                        const li = document.createElement('li');
                        const hasKids = !!(node.children && node.children.length);
                        const expanded = depth < 2;
                        const sc = node.scope_level || '';
                        const badges = `<span class="ochip ${scopeCls[sc] || ''}">scope: <b>${esc(sc)}</b></span>` +
                            (node.can_access_web ? `<span class="ochip">web <b class="text-emerald-600 dark:text-emerald-400">✓</b></span>` : `<span class="ochip">web <b class="text-gray-400">✗</b></span>`) +
                            (hasKids ? `<span class="ochip" style="background:rgb(245 158 11);color:#fff;border-color:rgb(245 158 11)"><b style="color:#fff">${node.children.length}</b></span>` : '');
                        li.innerHTML = `<div class="${cardBase} ${lvlRing.role}">` +
                            (hasKids ? `<span class="toggle"></span>` : '') +
                            `<div class="oname">${esc(node.name)}</div>` +
                            `<div class="obadges">${badges}</div></div>`;
                        if (hasKids) {
                            const ul = document.createElement('ul');
                            ul.style.display = expanded ? 'flex' : 'none';
                            if (!expanded) li.querySelector('.orgcard').classList.add('is-collapsed');
                            node.children.forEach(c => renderRole(c, ul, depth + 1));
                            li.appendChild(ul);
                        }
                        parentEl.appendChild(li);
                    };

                    this.renderOrgTree(false);
                    (this.data.roles || []).forEach(r => renderRole(r, this.$refs.roleTree, 0));

                    ['orgTree', 'roleTree'].forEach(ref => {
                        const el = this.$refs[ref];
                        if (el.dataset.diagramListener === 'true') return;
                        el.dataset.diagramListener = 'true';

                        el.addEventListener('click', e => {
                            const userButton = e.target.closest('.ochip-user');
                            if (userButton) {
                                e.preventDefault();
                                e.stopPropagation();
                                this.showUserPopover(userButton);
                                return;
                            }
                            if (this.suppressNextCardClick) return;
                            const card = e.target.closest('.orgcard');
                            if (!card) return;
                            const li = card.parentElement;
                            const ul = li.querySelector(':scope > ul');
                            if (!ul) return;
                            const open = ul.style.display === 'flex';
                            ul.style.display = open ? 'none' : 'flex';
                            card.classList.toggle('is-collapsed', open);
                            this.$nextTick(() => this.keepCardInView(card));
                        });
                    });
                },
                expandAll(refName, open) {
                    const refs = { org: 'orgTree', role: 'roleTree' };
                    const el = this.$refs[refs[refName] || refName];
                    if (!el) return;
                    el.querySelectorAll('li > ul').forEach(ul => {
                        ul.style.display = open ? 'flex' : 'none';
                        const card = ul.parentElement.querySelector(':scope > .orgcard');
                        if (card) card.classList.toggle('is-collapsed', !open);
                    });
                    if (refName === 'org') this.$nextTick(() => this.centerOrgRoot());
                },
                async callLivewire(method, ...args) {
                    const wire = this.$wire ?? this.$root?._x_livewire;
                    if (!wire) {
                        throw new Error('Livewire tidak tersedia');
                    }
                    if (typeof wire[method] === 'function') {
                        return await wire[method](...args);
                    }
                    return await wire.call(method, ...args);
                },
                async showUserPopover(button) {
                    const nodeName = button.dataset.nodeName || '-';
                    const areaLabel = button.dataset.areaLabel || 'area';
                    const scopeId = parseInt(button.dataset.nodeId || '0', 10) || null;
                    const level = button.dataset.level || 'bu';
                    const cacheKey = `${level}:${scopeId}`;
                    this.$refs.orgTree?.querySelectorAll('.ochip-user.is-active').forEach(el => el.classList.remove('is-active'));
                    button.classList.add('is-active');
                    this.expandedUserAreas = {};

                    if (this.userCache[cacheKey]) {
                        this.hoveredNodeUsers = {
                            title: nodeName,
                            areaLabel,
                            scopeId,
                            users: this.userCache[cacheKey],
                            loading: false,
                            error: false,
                        };
                        return;
                    }

                    const fetchToken = ++this.userFetchToken;
                    this.hoveredNodeUsers = {
                        title: nodeName,
                        areaLabel,
                        scopeId,
                        users: [],
                        loading: true,
                        error: false,
                    };
                    try {
                        const users = await this.callLivewire('fetchScopeUsers', scopeId, level);
                        if (fetchToken !== this.userFetchToken) return;
                        const list = Array.isArray(users) ? users : [];
                        this.userCache[cacheKey] = list;
                        this.hoveredNodeUsers = {
                            title: nodeName,
                            areaLabel,
                            scopeId,
                            users: list,
                            loading: false,
                            error: false,
                        };
                    } catch (error) {
                        if (fetchToken !== this.userFetchToken) return;
                        this.hoveredNodeUsers = {
                            title: nodeName,
                            areaLabel,
                            scopeId,
                            users: [],
                            loading: false,
                            error: true,
                        };
                    }
                },
                closeUserPopover() {
                    this.hoveredNodeUsers = null;
                    this.expandedUserAreas = {};
                    this.$refs.orgTree?.querySelectorAll('.ochip-user.is-active').forEach(el => el.classList.remove('is-active'));
                },
                formatPersonName(name) {
                    return this.formatLabel(name);
                },
                formatArea(area) {
                    return this.formatLabel(area?.name || area?.code || '');
                },
                userInitials(name) {
                    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return '?';
                    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
                    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
                },
                formatLabel(value) {
                    const raw = String(value ?? '').trim();
                    if (!raw) return '-';
                    const normalized = raw.replace(/_/g, ' ');
                    if (this.isCodeLabel(normalized)) {
                        return normalized;
                    }
                    return normalized.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
                },
                isCodeLabel(value) {
                    const compact = value.replace(/\s/g, '');
                    if (/^[A-Z0-9]+(?:[.\-][A-Z0-9]+)+$/.test(compact)) {
                        return true;
                    }
                    return compact.length <= 10 && compact === compact.toUpperCase() && /^[A-Z0-9.\-]+$/.test(compact);
                },
                shouldShowCoverage(user) {
                    return (user?.areas?.length || 0) > 1;
                },
                sortedAreas(user) {
                    const scopeId = this.hoveredNodeUsers?.scopeId;
                    return [...(user?.areas || [])].sort((a, b) => {
                        if (a.id === scopeId) return -1;
                        if (b.id === scopeId) return 1;
                        return this.formatArea(a).localeCompare(this.formatArea(b));
                    });
                },
                otherAreas(user) {
                    const scopeId = this.hoveredNodeUsers?.scopeId;
                    return this.sortedAreas(user).filter(area => area.id !== scopeId);
                },
                coverageSummary(user) {
                    const others = this.otherAreas(user);
                    if (!others.length) return '';
                    const preview = others.slice(0, 3).map(area => this.formatArea(area));
                    const remaining = others.length - preview.length;
                    if (remaining > 0) preview.push(`+${remaining} lainnya`);
                    return preview.join(', ');
                },
                isUserAreasExpanded(userId) {
                    return !!this.expandedUserAreas[userId];
                },
                toggleUserAreas(userId) {
                    this.expandedUserAreas[userId] = !this.expandedUserAreas[userId];
                },
                filteredOrg() {
                    if (this.selectedBadanUsaha === 'all') return this.data.org || [];
                    return (this.data.org || []).filter(item => String(item.id) === String(this.selectedBadanUsaha));
                },
                renderOrgTree(keepScroll = true) {
                    if (!this.orgRenderer || !this.$refs.orgTree) return;
                    this.closeUserPopover();
                    const viewport = this.$refs.orgViewport;
                    const previousPanX = this.orgPanX;
                    const previousPanY = this.orgPanY;
                    this.$refs.orgTree.replaceChildren();
                    this.filteredOrg().forEach(bu => this.orgRenderer(bu, 'bu', this.$refs.orgTree, 0));
                    this.applyOrgZoom();
                    this.$nextTick(() => {
                        if (!viewport) return;
                        if (keepScroll && this.selectedBadanUsaha === 'all') {
                            this.orgPanX = previousPanX;
                            this.orgPanY = previousPanY;
                            this.applyOrgZoom();
                            return;
                        }
                        this.centerOrgRoot();
                    });
                },
                centerOrgRoot() {
                    const viewport = this.$refs.orgViewport;
                    const firstRoot = this.$refs.orgTree?.querySelector(':scope > li > .orgcard');
                    if (!viewport || !firstRoot) return;
                    this.orgPanX = (viewport.clientWidth / 2) - ((firstRoot.offsetLeft + (firstRoot.offsetWidth / 2)) * this.orgZoom);
                    this.orgPanY = 72;
                    this.applyOrgZoom();
                },
                keepCardInView(card) {
                    const viewport = this.$refs.orgViewport;
                    if (!viewport || !card) return;
                    const viewportRect = viewport.getBoundingClientRect();
                    const cardRect = card.getBoundingClientRect();
                    const padding = 32;
                    let dx = 0, dy = 0;
                    if (cardRect.left < viewportRect.left + padding) dx = (viewportRect.left + padding) - cardRect.left;
                    else if (cardRect.right > viewportRect.right - padding) dx = (viewportRect.right - padding) - cardRect.right;
                    if (cardRect.top < viewportRect.top + padding) dy = (viewportRect.top + padding) - cardRect.top;
                    else if (cardRect.bottom > viewportRect.bottom - padding) dy = (viewportRect.bottom - padding) - cardRect.bottom;
                    if (dx || dy) { this.orgPanX += dx; this.orgPanY += dy; this.applyOrgZoom(); }
                },
                applyOrgZoom() {
                    if (!this.$refs.orgTree) return;
                    this.$refs.orgTree.style.transform = `translate3d(${this.orgPanX}px, ${this.orgPanY}px, 0) scale(${this.orgZoom})`;
                },
                zoomIn() { this.zoomAtCenter(1.14); },
                zoomOut() { this.zoomAtCenter(1 / 1.14); },
                resetZoom() { this.orgZoom = 1; this.$nextTick(() => this.centerOrgRoot()); },
                toggleCanvasFullscreen() {
                    this.isCanvasFullscreen = !this.isCanvasFullscreen;
                    document.body.style.overflow = this.isCanvasFullscreen ? 'hidden' : '';
                    this.$nextTick(() => requestAnimationFrame(() => this.centerOrgRoot()));
                },
                zoomAtCenter(factor) {
                    const viewport = this.$refs.orgViewport;
                    if (!viewport) return;
                    const rect = viewport.getBoundingClientRect();
                    this.zoomAt(rect.left + (rect.width / 2), rect.top + (rect.height / 2), factor);
                },
                zoomWheel(event) {
                    const factor = event.deltaY < 0 ? 1.08 : 1 / 1.08;
                    this.zoomAt(event.clientX, event.clientY, factor);
                },
                zoomAt(clientX, clientY, factor) {
                    const viewport = this.$refs.orgViewport;
                    if (!viewport) return;
                    const previousZoom = this.orgZoom;
                    const nextZoom = Math.min(Math.max(previousZoom * factor, 0.35), 1.8);
                    if (nextZoom === previousZoom) return;
                    const rect = viewport.getBoundingClientRect();
                    const focalX = clientX - rect.left;
                    const focalY = clientY - rect.top;
                    const worldX = (focalX - this.orgPanX) / previousZoom;
                    const worldY = (focalY - this.orgPanY) / previousZoom;
                    this.orgZoom = Math.round(nextZoom * 100) / 100;
                    this.orgPanX = focalX - (worldX * this.orgZoom);
                    this.orgPanY = focalY - (worldY * this.orgZoom);
                    this.applyOrgZoom();
                },
                startPan(event) {
                    if (event.button !== 0 || event.target.closest('.orgcard, .ochip-user, .dj-user-panel, .dj-float-controls, .dj-legend')) return;
                    this.panState = {
                        pointerId: event.pointerId, startX: event.clientX, startY: event.clientY,
                        panX: this.orgPanX, panY: this.orgPanY, moved: false,
                    };
                    this.$refs.orgViewport?.classList.add('is-panning');
                    event.currentTarget.setPointerCapture?.(event.pointerId);
                },
                panCanvas(event) {
                    if (!this.panState || this.panState.pointerId !== event.pointerId) return;
                    const dx = event.clientX - this.panState.startX;
                    const dy = event.clientY - this.panState.startY;
                    if (Math.abs(dx) + Math.abs(dy) > 4) this.panState.moved = true;
                    this.orgPanX = this.panState.panX + dx;
                    this.orgPanY = this.panState.panY + dy;
                    this.applyOrgZoom();
                },
                stopPan() {
                    if (this.panState?.moved) {
                        this.suppressNextCardClick = true;
                        setTimeout(() => { this.suppressNextCardClick = false; }, 0);
                    }
                    this.panState = null;
                    this.$refs.orgViewport?.classList.remove('is-panning');
                },
            };
        };
    </script>
</x-filament-panels::page>
