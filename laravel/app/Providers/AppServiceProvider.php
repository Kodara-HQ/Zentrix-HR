<?php

namespace App\Providers;

use App\Models\Notification;
use App\Support\Tenant;
use App\Support\Theme;
use App\Support\TenantPresenceVerifier;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // exists: and unique: rules only see the current company's rows.
        $this->callAfterResolving('validator', function ($factory, $app) {
            $factory->setPresenceVerifier(new TenantPresenceVerifier($app['db']));
        });

        // Company name and unread count appear in every layout.
        View::composer(['components.layouts.*', 'auth.*'], function ($view) {
            $user = auth()->user();
            $record = rescue(fn () => Tenant::company(), null, false);
            $view->with([
                'company' => $record?->name ?: 'Zentrix HR',
                'themeCss' => Theme::css($record),
                'unread' => $user ? Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
            ]);
        });
    }
}
