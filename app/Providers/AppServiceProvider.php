<?php

namespace App\Providers;

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
        \App\Models\Run::observe(\App\Observers\RunObserver::class);
        \App\Models\RunCommitment::observe(\App\Observers\RunCommitmentObserver::class);
    }
}
