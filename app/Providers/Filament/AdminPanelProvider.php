<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->brandLogo(asset('icon/samsam.png'))
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarWidth('18rem')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->navigationItems([
                NavigationItem::make('Telescope')
                    ->url(fn () => url(env('TELESCOPE_PATH', 'telescope')))
                    ->icon('heroicon-o-magnifying-glass-circle')
                    ->group('Developer')
                    ->openUrlInNewTab()
                    ->visible(fn () => auth()->user()?->role->name === 'SUPER ADMIN'),
                NavigationItem::make('api-docs')
                    ->label('API Docs')
                    ->url('/docs/api', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-code-bracket-square')
                    ->visible(fn () => auth()->user()?->role->name === 'SUPER ADMIN')
                    ->group('Developer'),
                NavigationItem::make('pulse')
                    ->label('Monitoring Server')
                    ->url('/pulse', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-server-stack')
                    ->visible(fn () => auth()->user()?->role->name === 'SUPER ADMIN')
                    ->group('Developer'),
                NavigationItem::make('old-dashboard')
                    ->label('Old Dashboard')
                    ->url('/dashboard', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-tv')
                    ->group('Settings'),
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,

            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
