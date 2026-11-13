<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\ForgotPassword;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\ResetPasswordWithOtp;
use App\Support\FestiveThemes;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            // The original PNG has a white tagline, so it only reads on dark surfaces.
            ->brandLogo(asset('images/globalspace-logo-light.png'))
            ->darkModeBrandLogo(asset('images/globalspace-logo.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/globalspace-favicon.png'))
            // Normal GlobalSpace colours, or the active celebration theme's (Organization → Celebration Themes).
            ->colors(fn (): array => FestiveThemes::colors())
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): string => view('filament.partials.sister-company-logo')->render())
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => view('filament.partials.login-logos')->render(),
                scopes: Login::class,
            )
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => FestiveThemes::render('head'))
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => FestiveThemes::render('stage'),
                scopes: [Login::class, ForgotPassword::class, ResetPasswordWithOtp::class],
            )
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): string => FestiveThemes::render('greeting'))
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): string => FestiveThemes::render('welcome'),
                scopes: Pages\Dashboard::class,
            )
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
                'Performance',
                'Payroll',
                'Income Tax',
                'Expenses',
                'Loans & Advances',
                'Onboarding',
                'Exit Management',
                'Field Force',
                'Organization',
                'Policies',
                'File Sharing',
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
                \App\Http\Middleware\ApplyFestivePreview::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
