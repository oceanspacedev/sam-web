@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Filament\Support\Enums\Width;
    use Illuminate\Support\Arr;

    $user = filament()->auth()->user();

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    $userName = filament()->getUserName($user);
    $userEmail = $user->email ?? null;
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="($position === UserMenuPosition::Topbar) ? 'bottom-end' : 'top-end'"
    :teleport="$position === UserMenuPosition::Topbar"
    :width="Width::ExtraSmall"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu mky-user-menu'])
    "
>
    <x-slot name="trigger">
        @if ($position === UserMenuPosition::Topbar)
            <div class="group relative flex items-center rounded-xl transition duration-200 ease-in-out">
                <button
                    aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                    type="button"
                    class="fi-user-menu-trigger mky-user-menu-trigger"
                >
                    <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                    <span
                        class="absolute right-0 bottom-0 block size-2.5 rounded-full bg-success-400 ring-2 ring-white dark:ring-white/10"
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        @else
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-user-menu-trigger"
            >
                <x-filament-panels::avatar.user :user="$user" loading="lazy" />

                <span
                    @if ($isSidebarCollapsibleOnDesktop)
                        x-show="! $store.sidebar.isCollapsed"
                    @endif
                    class="fi-user-menu-trigger-text"
                >
                    {{ $userName }}
                </span>

                {{
                    \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronUp, alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON, attributes: new \Illuminate\View\ComponentAttributeBag([
                        'x-show' => $isSidebarCollapsibleOnDesktop ? '! $store.sidebar.isCollapsed' : null,
                    ]))
                }}
            </button>
        @endif
    </x-slot>

    <div class="mky-user-menu-account">
        <x-filament-panels::avatar.user :user="$user" loading="lazy" />

        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                {{ $userName }}
            </p>

            @if (filled($userEmail))
                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                    {{ $userEmail }}
                </p>
            @endif
        </div>
    </div>

    @if ($hasProfileHeader)
        @php
            $item = $itemsBeforeThemeSwitcher['profile'];
            $itemColor = $item->getColor();
            $itemIcon = $item->getIcon();

            unset($itemsBeforeThemeSwitcher['profile']);
        @endphp

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
            {{ $item->getLabel() }}
        </x-filament::dropdown.header>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    @if ($itemsBeforeThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list class="mky-user-menu-list">
            @foreach ($itemsBeforeThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if ($itemsAfterThemeSwitcher->isNotEmpty())
        <x-filament::dropdown.list class="mky-user-menu-list">
            @foreach ($itemsAfterThemeSwitcher as $key => $item)
                @if ($key === 'profile')
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                    {{ $item }}

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                @else
                    {{ $item }}
                @endif
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
        <x-filament::dropdown.list class="mky-user-menu-theme">
            <x-mekaya::theme-switcher />
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
