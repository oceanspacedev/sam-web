<x-filament-panels::page>
    @php $s = $treeData['summary']; @endphp

    <div x-data="diagramJabatanChart(@js($treeData))" x-init="init()" x-on:keydown.escape.window="isCanvasFullscreen && toggleCanvasFullscreen()" x-cloak class="space-y-8">

        {{-- Stat tiles (shopper nested-ring) --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @php
                $tiles = [
                    ['Badan Usaha', $s['badan_usaha_active'], $s['badan_usaha_total'], 'heroicon-o-building-office-2', 'indigo'],
                    ['Division',    $s['division_active'],    $s['division_total'],    'heroicon-o-rectangle-stack', 'sky'],
                    ['Region',      $s['region_active'],      $s['region_total'],      'heroicon-o-map',             'teal'],
                    ['Cluster',     $s['cluster_active'],     $s['cluster_total'],     'heroicon-o-squares-2x2',     'amber'],
                    ['User',        $s['user_active'],        $s['user_total'],        'heroicon-o-users',          'emerald'],
                    ['Role',        $s['role_active'],        $s['role_total'],        'heroicon-o-shield-check',   'violet'],
                ];
            @endphp
            @foreach ($tiles as $t)
                <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $t[0] }}</span>
                        <x-filament::icon :icon="$t[3]" class="size-4 text-gray-300 dark:text-gray-600" />
                    </div>
                    <p class="mt-2 font-sans text-2xl font-bold text-gray-900 dark:text-white">{{ $t[1] }}</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">/ {{ $t[2] }} total</p>
                </div>
            @endforeach
        </div>

        {{-- Tabs (shopper underline) --}}
        <nav class="-mb-px flex space-x-6 overflow-x-auto border-b border-gray-200 dark:border-white/10">
            <button type="button" x-on:click="pane = 'org'" @class(['border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors', 'border-primary-500 text-primary-600 dark:text-primary-400' => "pane === 'org'", 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400' => "pane !== 'org'"])>
                1. Hirarki Organisasi
            </button>
            <button type="button" x-on:click="pane = 'roles'" @class(['border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors', 'border-primary-500 text-primary-600 dark:text-primary-400' => "pane === 'roles'", 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400' => "pane !== 'roles'"])>
                2. Pohon Peran
            </button>
            <button type="button" x-on:click="pane = 'tm'" @class(['border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors', 'border-primary-500 text-primary-600 dark:text-primary-400' => "pane === 'tm'", 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400' => "pane !== 'tm'"])>
                3. Hirarki Tim
            </button>
        </nav>

        {{-- Toolbar + legend (shopper small buttons) --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">Badan Usaha</span>
                <select x-model="selectedBadanUsaha" x-on:change="renderOrgTree()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">
                    <option value="all">Semua Badan Usaha</option>
                    <template x-for="item in orgOptions" :key="item.id">
                        <option :value="String(item.id)" x-text="item.name"></option>
                    </template>
                </select>
            </label>

            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-1 text-sm tabular-nums text-gray-400 dark:text-gray-500" x-text="`${Math.round(orgZoom * 100)}%`"></span>
                <button type="button" x-on:click="zoomOut()" class="dj-btn" title="Zoom out"><x-filament::icon icon="heroicon-o-magnifying-glass-minus" class="size-4" /></button>
                <button type="button" x-on:click="zoomIn()" class="dj-btn" title="Zoom in"><x-filament::icon icon="heroicon-o-magnifying-glass-plus" class="size-4" /></button>
                <button type="button" x-on:click="resetZoom()" class="dj-btn" title="Reset">Reset</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-white/10"></span>
                <button type="button" x-on:click="expandAll('org', true)" class="dj-btn"><x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-4" /> Expand</button>
                <button type="button" x-on:click="expandAll('org', false)" class="dj-btn"><x-filament::icon icon="heroicon-o-bars-3-bottom-left" class="size-4" /> Collapse</button>
                <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-white/10"></span>
                <button type="button" x-on:click="toggleCanvasFullscreen()" class="dj-btn-primary">
                    <x-filament::icon icon="heroicon-o-window" class="size-4" />
                    <span x-text="isCanvasFullscreen ? 'Keluar' : 'Fullscreen'"></span>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-indigo-500"></span>Badan Usaha</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-sky-500"></span>Division</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-teal-500"></span>Region</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-500"></span>Cluster</span>
            <span class="text-gray-400 dark:text-gray-500">Klik kotak untuk expand/collapse · klik chip <b class="font-medium text-gray-600 dark:text-gray-300">User</b> untuk daftar user · drag area kosong untuk geser · scroll untuk zoom.</span>
        </div>

        {{-- ORG canvas --}}
        <div x-show="pane==='org'" class="dj-panel">
            <div
                x-ref="orgViewport"
                class="orgchart-canvas rounded-xl ring-1 ring-gray-200 dark:ring-white/10"
                x-bind:class="{ 'is-fullscreen': isCanvasFullscreen }"
                x-on:pointerdown="startPan($event)"
                x-on:pointermove.window="panCanvas($event)"
                x-on:pointerup.window="stopPan()"
                x-on:pointercancel.window="stopPan()"
                x-on:wheel.prevent="zoomWheel($event)"
            >
                <ul x-ref="orgTree" class="orgchart !list-none !m-0 !p-0"></ul>

                {{-- User popover --}}
                <div
                    x-show="hoveredNodeUsers"
                    x-cloak
                    x-transition.opacity.duration.120ms
                    class="dj-user-popover rounded-xl bg-white p-3 ring-1 ring-gray-200 shadow-lg dark:bg-gray-900 dark:ring-white/10"
                    x-on:pointerdown.stop x-on:click.stop x-on:wheel.stop
                >
                    <div class="flex items-start justify-between gap-3 pb-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Daftar user akses</p>
                            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white" x-text="hoveredNodeUsers?.title"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="hoveredNodeUsers?.subtitle"></p>
                        </div>
                        <button type="button" x-on:click="closeUserPopover()" aria-label="Tutup" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10 dark:hover:text-white">×</button>
                    </div>
                    <template x-if="hoveredNodeUsers && hoveredNodeUsers.users.length === 0">
                        <p class="py-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada user aktif dengan akses tepat di level ini.</p>
                    </template>
                    <div class="grid gap-2" x-show="hoveredNodeUsers && hoveredNodeUsers.users.length > 0">
                        <template x-for="user in hoveredNodeUsers?.users || []" :key="user.id">
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white" x-text="user.name"></p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        <span x-text="user.role"></span>
                                        <span x-show="user.username" x-text="`@${user.username}`"></span>
                                    </p>
                                </div>
                                <template x-if="user.is_multi">
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400" x-text="`${user.assignment_count} area`"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ROLES tree --}}
        <div x-show="pane==='roles'" x-cloak class="dj-panel">
            <div class="flex flex-wrap items-center gap-2 pb-1">
                <button type="button" x-on:click="expandAll('role', true)" class="dj-btn"><x-filament::icon icon="heroicon-o-arrows-pointing-out" class="size-4" /> Expand</button>
                <button type="button" x-on:click="expandAll('role', false)" class="dj-btn"><x-filament::icon icon="heroicon-o-bars-3-bottom-left" class="size-4" /> Collapse</button>
                <span class="text-xs text-gray-400 dark:text-gray-500">Pohon <code class="font-mono">parent_role_id</code> = lineage pelaporan, bukan filter akses data.</span>
            </div>
            <div class="orgchart-scroll rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                <ul x-ref="roleTree" class="orgchart !list-none !m-0 !p-0"></ul>
            </div>
        </div>

        {{-- TM cards --}}
        <div x-show="pane==='tm'" x-cloak class="dj-panel space-y-4">
            <p class="text-xs text-gray-500 dark:text-gray-400">Top team lead via <code class="font-mono">tm_id</code> · anggota mengecualikan baris self-referencing.</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($treeData['tm_leads'] as $lead)
                    <div class="group flex flex-col justify-between overflow-hidden rounded-xl bg-white p-4 ring-1 ring-emerald-200 dark:bg-gray-900 dark:ring-emerald-500/30">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $lead['member_count'] }} anggota</span>
                            <span class="inline-flex size-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400">
                                <x-filament::icon icon="heroicon-o-user-group" class="size-4" />
                            </span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $lead['lead_name'] }}</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $lead['role'] }} · id#{{ $lead['lead_id'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                <b class="font-medium text-gray-700 dark:text-gray-200">Catatan:</b> <code class="font-mono">tm_id</code> dipakai untuk supervision/approval &amp; grouping, <b class="font-medium text-gray-700 dark:text-gray-200">bukan</b> batas akses data. Data anggota tim tetap ditentukan oleh role dan area kerja masing-masing.
            </div>
        </div>

    </div>

    <style>
        [x-cloak]{display:none!important}
        .dj-panel{display:grid;gap:1rem}

        /* Small bordered buttons (shopper toolbar style) */
        .dj-btn{display:inline-flex;align-items:center;gap:.375rem;border-radius:.5rem;border:1px solid rgb(229 231 235);background:#fff;padding:.375rem .625rem;font-size:.75rem;font-weight:500;color:#374151;transition:background-color .12s,color .12s}
        .dj-btn:hover{background:#f9fafb;color:#111827}
        .dark .dj-btn{border-color:rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#d1d5db}
        .dark .dj-btn:hover{background:rgba(255,255,255,.1);color:#fff}
        .dj-btn-primary{display:inline-flex;align-items:center;gap:.375rem;border-radius:.5rem;background:var(--c-primary-600,#d97706);padding:.375rem .75rem;font-size:.75rem;font-weight:600;color:#fff}
        .dj-btn-primary:hover{opacity:.92}
        .dark .dj-btn-primary{background:var(--c-primary-500,#f59e0b)}

        /* Canvas + tree (mechanics only — cannot be Tailwind utilities) */
        .orgchart-canvas{overflow:hidden;position:relative;height:min(72vh,720px);cursor:grab;touch-action:none;user-select:none;background:rgba(249,250,251,.6)}
        .orgchart-canvas.is-panning{cursor:grabbing}
        .orgchart-canvas.is-fullscreen{position:fixed;inset:16px;z-index:60;height:auto;border-radius:12px;background:#f8fafc;box-shadow:0 24px 80px rgba(15,23,42,.28)}
        .dark .orgchart-canvas{background:rgba(3,7,18,.32)}
        .dark .orgchart-canvas.is-fullscreen{background:#020617;box-shadow:0 24px 80px rgba(0,0,0,.65)}
        .orgchart-scroll{overflow:auto;padding:18px 20px 26px;max-height:min(70vh,700px)}

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

        /* Org-card box (tokenized via Tailwind classes on the element; only layout here) */
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

        .dj-user-popover{position:absolute;z-index:50;top:12px;right:12px;width:min(360px,calc(100% - 24px));max-height:calc(100% - 24px);overflow:auto;box-shadow:0 18px 44px rgba(15,23,42,.2)}
        .dark .dj-user-popover{box-shadow:0 18px 40px rgba(0,0,0,.45)}

        @media (max-width:720px){
            .orgchart-canvas{height:520px}
            .orgchart-canvas.is-fullscreen{inset:8px;border-radius:10px}
            .orgcard{min-width:160px}
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
                        const users = node.scope_users || [];
                        return `<button type="button" class="ochip ochip-action ochip-user" data-users='${esc(JSON.stringify(users))}' data-node-name="${esc(node.name || '-')}" data-node-code="${esc(node.code || '')}" data-area-label="${esc(levelLabels[level] || 'area')}" aria-label="Lihat daftar user ${esc(node.name || '-')}">User <b>${users.length}</b></button>`;
                    };

                    // Ring-based level color (Tailwind classes compiled from this file's markup usage)
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

                        const showFromUserChip = e => {
                            const userButton = e.target.closest('.ochip-action');
                            if (userButton) this.showUserPopover(userButton);
                        };
                        el.addEventListener('pointerover', showFromUserChip);
                        el.addEventListener('mouseover', showFromUserChip);
                        el.addEventListener('focusin', e => {
                            const userButton = e.target.closest('.ochip-action');
                            if (userButton) this.showUserPopover(userButton);
                        });
                        el.addEventListener('click', e => {
                            const userButton = e.target.closest('.ochip-action');
                            if (userButton) {
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
                showUserPopover(button) {
                    const users = JSON.parse(button.dataset.users || '[]');
                    const nodeName = button.dataset.nodeName || '-';
                    const nodeCode = button.dataset.nodeCode || '';
                    const areaLabel = button.dataset.areaLabel || 'area';
                    this.$refs.orgTree?.querySelectorAll('.ochip-action.is-active').forEach(el => el.classList.remove('is-active'));
                    button.classList.add('is-active');
                    this.hoveredNodeUsers = {
                        title: `User: ${nodeName}`,
                        subtitle: nodeCode ? `${nodeCode} - level akses ${areaLabel}` : `Level akses ${areaLabel}`,
                        areaLabel, users,
                    };
                },
                closeUserPopover() {
                    this.hoveredNodeUsers = null;
                    this.$refs.orgTree?.querySelectorAll('.ochip-action.is-active').forEach(el => el.classList.remove('is-active'));
                },
                filteredOrg() {
                    if (this.selectedBadanUsaha === 'all') return this.data.org || [];
                    return (this.data.org || []).filter(item => String(item.id) === String(this.selectedBadanUsaha));
                },
                renderOrgTree(keepScroll = true) {
                    if (!this.orgRenderer || !this.$refs.orgTree) return;
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
                    this.orgPanY = 16;
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
                    if (event.button !== 0 || event.target.closest('.orgcard')) return;
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