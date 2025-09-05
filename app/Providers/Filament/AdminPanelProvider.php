<?php

namespace App\Providers\Filament;

use App\Models\Branch;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\DirectorDashboard;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('director')
            ->path('director')
            ->login()
            ->tenant(Branch::class)
            ->tenantBillingProvider(null)
            ->brandName('Wellness Director Portal')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Pink,
            ])
            ->maxContentWidth('full')
            ->sidebarWidth('16rem')
            ->renderHook(
                'panels::styles.before',
                fn () => '<style>
                    .fi-main { 
                        padding-left: 1rem !important; 
                        padding-right: 1rem !important; 
                        max-width: none !important; 
                    }
                    @media (min-width: 1024px) { 
                        .fi-main { 
                            padding-left: 1.5rem !important; 
                            padding-right: 1.5rem !important; 
                        } 
                    }
                    .fi-main-content { 
                        max-width: none !important; 
                        width: 100% !important; 
                    }
                </style>'
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                DirectorDashboard::class,
            ])
            ->navigationGroups([
                'Dashboard',
                'Business Operations',
                'Customer Management',
                'Staff & Scheduling',
                'Financial Management',
                'Inventory & Products',
                'Marketing & Promotions',
                'System Administration',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
            ]);
    }
}
