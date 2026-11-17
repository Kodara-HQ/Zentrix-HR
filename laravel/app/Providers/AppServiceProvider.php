<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Notification;
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
        // Company name and unread count appear in every layout.
        View::composer(['components.layouts.*', 'auth.*'], function ($view) {
            static $company = null;
            $company ??= rescue(fn () => Company::query()->value('name'), null, false) ?: 'Zentrix Corporation';
            $user = auth()->user();
            $view->with([
                'company' => $company,
                'unread' => $user ? Notification::where('user_id', $user->id)->whereNull('read_at')->count() : 0,
            ]);
        });
    }
}
