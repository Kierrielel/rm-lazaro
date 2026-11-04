<?php

namespace App\Providers;

use App\Models\StoreSetting;
use Illuminate\Pagination\Paginator;
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
        Paginator::useBootstrapFive();
        View::composer(['layouts.customer', 'layouts.admin'], function (\Illuminate\View\View $view): void {
            $view->with('storeSettings', StoreSetting::current());
        });
    }
}
