<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\ForgotPassword;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPasswordWithOtp;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset(ForgotPassword::class, ResetPasswordWithOtp::class)
            ->profile(ChangePassword::class)
            ->brandName('GlobalSpace HRMS')
            ->brandLogo(asset('images/globalspace-logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/globalspace-favicon.png'))
            ->colors([
                'primary' => Color::hex('#0F7A78'),
                'warning' => Color::hex('#F5A623'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Menu order: self-service & daily-use groups first, configuration/admin last.
            ->navigationGroups([
                'Employees',
                'Attendance',
                'Leave',
                'Payroll',
                'Income Tax',
                'Expenses',
                'Loans & Advances',
                'Onboarding',
                'Exit Management',
                'Organization',
                'Policies',
                'Notifications',
                'Roles & Permissions',
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
