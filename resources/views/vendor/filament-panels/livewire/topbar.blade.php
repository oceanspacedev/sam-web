<div
    class="mky-header sticky top-0 z-20 flex h-16 shrink-0 border-b border-gray-200 bg-gray-50 lg:h-auto lg:rounded-tl-xl lg:py-2 dark:border-white/10 dark:bg-gray-950"
>
    <button
        @click.stop="$store.sidebar.open()"
        class="border-r border-gray-200 px-4 text-gray-500 lg:hidden dark:border-white/10"
        aria-label="Open sidebar"
    >
        <x-untitledui-menu-03 class="size-6" aria-hidden="true" />
    </button>

    @if (filament()->isSidebarCollapsibleOnDesktop())
        <button
            x-on:click="$store.sidebar.toggleCollapse()"
            class="hidden border-r border-gray-200 px-4 text-gray-500 hover:text-gray-700 lg:block dark:border-white/10 dark:text-gray-400 dark:hover:text-white"
            aria-label="{{ __('Toggle sidebar') }}"
        >
            <svg
                class="size-6 text-gray-400 dark:text-gray-500"
                viewBox="0 0 24 24"
                stroke="currentColor"
                fill="none"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M2.74902 6.75C2.74902 5.09315 4.09217 3.75 5.74902 3.75H18.2507C19.9075 3.75 21.2507 5.09315 21.2507 6.75V17.25C21.2507 18.9069 19.9075 20.25 18.2507 20.25H5.74902C4.09217 20.25 2.74902 18.9069 2.74902 17.25V6.75Z"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path d="M10.25 3.75V20.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5.75 7.75L7.25 7.75" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5.75 11L7.25 11" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M5.75 14.25L7.25 14.25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    @endif

    <div class="flex flex-1 items-center justify-between gap-4 px-4 lg:px-6">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        <div class="flex flex-1">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Topbar)
                @livewire(Filament\Livewire\GlobalSearch::class)
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}
        </div>
        <div class="flex items-center gap-x-3">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}

            <a
                href="{{ url('/') }}"
                target="_blank"
                class="hidden items-center rounded-lg p-1 text-gray-500 ring-1 ring-gray-200 hover:bg-gray-50 hover:text-gray-700 lg:inline-flex dark:text-gray-400 dark:ring-white/10 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                <x-filament::icon icon="heroicon-o-globe-alt" class="size-6" />
            </a>

            @if (filament()->auth()->check())
                @php
                    $currentPanel = filament()->getCurrentOrDefaultPanel();
                    $dbNotificationsComponent = $currentPanel?->getDatabaseNotificationsLivewireComponent();
                    $hasLazyLoadedDatabaseNotifications = $currentPanel?->hasLazyLoadedDatabaseNotifications() ?? false;
                @endphp

                @if (mekaya_database_notifications_enabled() && mekaya_database_notifications_position() === \Filament\Enums\DatabaseNotificationsPosition::Topbar && $dbNotificationsComponent)
                    @livewire($dbNotificationsComponent, [
                        'lazy' => $hasLazyLoadedDatabaseNotifications,
                    ])
                @endif

                @if (filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Topbar)
                    <x-filament-panels::user-menu />
                @endif
            @endif
        </div>
    </div>
</div>
