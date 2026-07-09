@php
    $data = $data ?? [];
    $rangeOptions = $rangeOptions ?? [];
    $range = $range ?? 'week';

    $viewer = $data['viewer'] ?? null;
    $period = $data['range'] ?? null;
    $permissions = $data['permissions'] ?? [];
    $cards = $data['cards'] ?? [];

    // Salam berbasis waktu + label yang lebih manusiawi (Title Case).
    $greeting = match (now()->hour) {
        5, 6, 7, 8, 9, 10 => 'Selamat pagi',
        11, 12, 13, 14 => 'Selamat siang',
        15, 16, 17, 18 => 'Selamat sore',
        default => 'Selamat malam',
    };
    $titleCase = fn ($value) => ucwords(mb_strtolower(trim((string) $value)));
    $viewerName = $viewer ? $titleCase($viewer['name']) : '';
    $viewerRole = $viewer ? $titleCase($viewer['role']) : '';
    $viewerScope = $viewer['scope'] ?? '';

    // Tone -> Tailwind classes (Filament semantic palette + neutral)
    $tone = [
        'success' => ['dot' => 'bg-success-500', 'icon' => 'heroicon-o-check-circle', 'text' => 'text-success-700 dark:text-success-400'],
        'danger'  => ['dot' => 'bg-danger-500',  'icon' => 'heroicon-o-exclamation-circle', 'text' => 'text-danger-700 dark:text-danger-400'],
        'warning' => ['dot' => 'bg-warning-500', 'icon' => 'heroicon-o-exclamation-triangle', 'text' => 'text-warning-700 dark:text-warning-400'],
        'neutral' => ['dot' => 'bg-gray-400 dark:bg-gray-600', 'icon' => 'heroicon-o-information-circle', 'text' => 'text-gray-600 dark:text-gray-400'],
    ];
    $toneOf = fn ($t) => $tone[$t] ?? $tone['neutral'];

    // Stat card icon by label keyword
    $iconFor = function (string $label): string {
        return match (true) {
            str_contains($label, 'Realisasi') => 'heroicon-o-calendar-days',
            str_contains($label, 'Plan')     => 'heroicon-o-clipboard-document-check',
            str_contains($label, 'Visit')    => 'heroicon-o-map-pin',
            str_contains($label, 'Register') => 'heroicon-o-document-text',
            str_contains($label, 'NOO')      => 'heroicon-o-check-badge',
            str_contains($label, 'Outlet')   => 'heroicon-o-building-storefront',
            str_contains($label, 'Team')     => 'heroicon-o-users',
            default                           => 'heroicon-o-chart-bar',
        };
    };

    $cardWrap = 'p-1 bg-gray-50 dark:bg-gray-950 rounded-xl ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden';
    $cardInner = 'rounded-lg bg-white p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10';
    $sectionHead = 'font-sans text-base font-semibold text-gray-900 dark:text-white';
    $sectionDesc = 'mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400';
    $empty = 'flex flex-col items-center justify-center px-6 py-10 text-center';
    $listRow = 'flex items-start justify-between gap-3 px-1 py-3';
@endphp

<div class="space-y-8">

    {{-- Header: operator + range filter (judul "Dashboard" dirender Filament sebagai page header) --}}
    <div class="space-y-3 sm:flex sm:items-start sm:justify-between sm:space-x-4 sm:space-y-0">
        <div>
            @if ($viewer)
                <p class="font-sans text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $greeting }}, {{ $viewerName }}
                </p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/30">{{ $viewerRole }}</span>
                    <span class="text-gray-400 dark:text-gray-500">·</span>
                    <span>Cakupan: <span class="font-medium text-gray-600 dark:text-gray-300">{{ $viewerScope }}</span></span>
                </p>
            @endif
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
            @if ($period)
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    {{ $period['label'] ?? '' }} · {{ $period['current'] ?? '' }}
                    @if (! empty($period['previous'])) <span class="italic">vs {{ $period['previous'] }}</span>@endif
                </p>
            @endif
            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 dark:border-white/10 dark:bg-gray-900" role="group" aria-label="Rentang waktu dashboard">
                @foreach ($rangeOptions as $key => $label)
                    <button
                        type="button"
                        wire:click="setRange('{{ $key }}')"
                        wire:key="opd-range-{{ $key }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            'bg-primary-600 text-white shadow-sm' => $range === $key,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $range !== $key,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    @if (empty($cards))
        <div class="{{ $cardWrap }}">
            <div class="{{ $cardInner }}">
                <div class="{{ $empty }}">
                    <x-filament::icon icon="heroicon-o-chart-bar" class="size-8 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $viewer ? 'Belum ada modul yang dapat dilihat untuk role Anda.' : 'Data dashboard belum tersedia untuk sesi ini.' }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Stat cards --}}
    @if (! empty($cards))
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cards as $card)
                @php $t = $toneOf($card['tone'] ?? 'neutral'); @endphp
                <a href="{{ $card['href'] ?? '#' }}" wire:navigate class="block {{ $cardWrap }} transition hover:ring-gray-300 dark:hover:ring-white/20">
                    <div class="{{ $cardInner }}">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ $card['label'] }}
                                @if (! empty($card['cumulative']))
                                    <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/10 dark:text-gray-400">Total</span>
                                @endif
                            </span>
                            <span class="size-2 rounded-full {{ $t['dot'] }}"></span>
                        </div>
                        <p class="mt-2 font-sans text-2xl font-bold text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $card['description'] }}</p>
                        <span class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400">
                            Lihat <x-filament::icon icon="heroicon-o-arrow-right" class="size-3.5" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Section grid --}}
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">

        {{-- PLAN --}}
        @if (($permissions['plan_visits'] ?? false) && ! empty($data['plan']))
            @php $plan = $data['plan']; @endphp
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Realisasi Plan Visit</h3>
                    <p class="{{ $sectionDesc }}">{{ $plan['realized'] ?? 0 }} terealisasi dari {{ $plan['due'] ?? 0 }} plan jatuh tempo.</p>
                </div>
                <div class="{{ $cardInner }}">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="font-sans text-3xl font-bold text-gray-900 dark:text-white">{{ $plan['realizedRate'] ?? 0 }}<span class="ml-0.5 text-lg font-semibold text-gray-400 dark:text-gray-500">%</span></p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tingkat realisasi</p>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                            <div><dt class="inline font-medium text-gray-700 dark:text-gray-200">{{ $plan['realized'] ?? 0 }}</dt> terealisasi</div>
                            <div><dt class="inline font-medium text-gray-700 dark:text-gray-200">{{ $plan['unrealizedDue'] ?? 0 }}</dt> belum realisasi</div>
                            <div><dt class="inline font-medium text-warning-700 dark:text-warning-400">{{ $plan['overdue'] ?? 0 }}</dt> lewat tanggal</div>
                            <div><dt class="inline font-medium text-gray-700 dark:text-gray-200">{{ $plan['due'] ?? 0 }}</dt> jatuh tempo</div>
                        </dl>
                    </div>

                    @if (! empty($plan['upcoming']))
                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Plan mendatang</p>
                            <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($plan['upcoming'] as $item)
                                    <li class="{{ $listRow }}">
                                        <a href="{{ $item['url'] ?? '#' }}" wire:navigate class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</span>
                                            <span class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</span>
                                        </a>
                                        <span class="shrink-0 text-sm tabular-nums text-gray-400 dark:text-gray-500">{{ $item['date'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- REGISTERS --}}
        @if (($permissions['registers'] ?? false) && ! empty($data['registers']))
            @php
                $regs = $data['registers'];
                $pipeline = $regs['pipeline'] ?? [];
                $pipeColor = ['success' => 'bg-success-500', 'danger' => 'bg-danger-500', 'warning' => 'bg-warning-500', 'neutral' => 'bg-gray-400'];
            @endphp
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Register &amp; Pipeline</h3>
                    <p class="{{ $sectionDesc }}">{{ $regs['needsValidation'] ?? 0 }} register butuh validasi (total) · periode ini: {{ $regs['approvedNoo'] ?? 0 }} NOO approved, {{ $regs['newLeads'] ?? 0 }} lead baru.</p>
                </div>
                <div class="{{ $cardInner }}">
                    @if (! empty($pipeline))
                        <div class="space-y-3">
                            @foreach ($pipeline as $stage)
                                <div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $stage['label'] }}</span>
                                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $stage['count'] }} · {{ $stage['percent'] }}%</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                        <div class="h-full rounded-full transition-all duration-700 ease-out {{ $pipeColor[$stage['tone'] ?? 'neutral'] ?? 'bg-gray-400' }}" style="width: {{ max($stage['percent'], $stage['count'] > 0 ? 3 : 0) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($regs['recent']))
                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Register terbaru</p>
                            <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($regs['recent'] as $item)
                                    <li class="{{ $listRow }}">
                                        <a href="{{ $item['url'] ?? '#' }}" wire:navigate class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</span>
                                            <span class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $item['meta'] }} · {{ $item['owner'] }}</span>
                                        </a>
                                        <span class="shrink-0 text-sm tabular-nums text-gray-400 dark:text-gray-500">{{ $item['date'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- VISITS --}}
        @if (($permissions['visits'] ?? false) && ! empty($data['visits']))
            @php $visits = $data['visits']; @endphp
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Aktivitas Visit</h3>
                    <p class="{{ $sectionDesc }}">Periode {{ $period['current'] ?? '' }} · {{ $visits['openCheckouts'] ?? 0 }} checkout belum ditutup (total, semua periode).</p>
                </div>
                <div class="{{ $cardInner }}">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="font-sans text-3xl font-bold text-gray-900 dark:text-white">{{ $visits['current'] ?? 0 }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Visit periode ini · sebelumnya {{ $visits['previous'] ?? 0 }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-sans text-xl font-bold text-warning-700 dark:text-warning-400">{{ $visits['openCheckouts'] ?? 0 }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">checkout terbuka</p>
                        </div>
                    </div>

                    @if (! empty($visits['topUsers']))
                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">User paling aktif</p>
                            <div class="mt-2 overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                                <table class="w-full table-fixed">
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Visit</th>
                                            <th class="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Target</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                        @foreach ($visits['topUsers'] as $i => $u)
                                            <tr class="{{ $loop->even ? 'bg-gray-50/50 dark:bg-white/[0.02]' : '' }}">
                                                <td class="px-3 py-2">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <span class="w-4 shrink-0 text-sm font-semibold tabular-nums text-gray-400 dark:text-gray-500">{{ $loop->index + 1 }}</span>
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $u['name'] }}</span>
                                                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $u['role'] }}</span>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2 text-right text-sm font-medium tabular-nums text-gray-700 dark:text-gray-300">{{ $u['visits'] }}</td>
                                                <td class="px-3 py-2 text-right text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ $u['targets'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if (! empty($visits['recent']))
                        <div class="mt-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Visit terbaru</p>
                            <ul class="mt-2 divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($visits['recent'] as $item)
                                    <li class="{{ $listRow }}">
                                        <a href="{{ $item['url'] ?? '#' }}" wire:navigate class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</span>
                                            <span class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $item['meta'] }} · {{ $item['status'] }}</span>
                                        </a>
                                        <span class="shrink-0 text-sm tabular-nums text-gray-400 dark:text-gray-500">{{ $item['date'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- OUTLETS --}}
        @if (($permissions['outlets'] ?? false) && ! empty($data['outlets']))
            @php $outlets = $data['outlets']; @endphp
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Kesehatan Data Outlet</h3>
                    <p class="{{ $sectionDesc }}">{{ $outlets['total'] ?? 0 }} outlet dalam scope · {{ $outlets['needsAttention'] ?? 0 }} perlu dibersihkan.</p>
                </div>
                <div class="{{ $cardInner }}">
                    @if (! empty($outlets['health']))
                        <div class="space-y-4">
                            @foreach ($outlets['health'] as $h)
                                <div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $h['label'] }}</span>
                                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $h['value'] }} / {{ $h['total'] }} ({{ $h['percent'] }}%)</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                        <div class="h-full rounded-full bg-success-500 transition-all duration-700 ease-out" style="width: {{ max($h['percent'], $h['value'] > 0 ? 3 : 0) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="{{ $empty }}">
                            <x-filament::icon icon="heroicon-o-building-storefront" class="size-8 text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada outlet dalam scope.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- TEAM --}}
        @if (($permissions['users'] ?? false) && ! empty($data['team']))
            @php $team = $data['team']; @endphp
            <div class="{{ $cardWrap }}">
                <div class="px-4 pt-4 pb-3">
                    <h3 class="{{ $sectionHead }}">Tim Dalam Scope</h3>
                    <p class="{{ $sectionDesc }}">Pengguna terlihat sesuai role &amp; area kerja operator.</p>
                </div>
                <div class="{{ $cardInner }}">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-lg bg-gray-50 p-4 text-center ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                            <p class="font-sans text-2xl font-bold text-gray-900 dark:text-white">{{ $team['visibleUsers'] ?? 0 }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">User terlihat</p>
                        </div>
                        @if (($permissions['visits'] ?? false))
                            <div class="rounded-lg bg-success-50 p-4 text-center ring-1 ring-success-200 dark:bg-success-500/10 dark:ring-success-500/30">
                                <p class="font-sans text-2xl font-bold text-success-700 dark:text-success-400">{{ $team['activeUsers'] ?? 0 }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Aktif bervisit</p>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-4 text-center ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <p class="font-sans text-2xl font-bold text-gray-500 dark:text-gray-400">{{ $team['inactiveUsers'] ?? 0 }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Belum bervisit periode ini</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

    </div>


</div>