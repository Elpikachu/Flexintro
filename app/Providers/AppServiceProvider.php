<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The header is how the presenter proves, in one glance, which
        // environment and which instance is answering.
        View::composer('layout', function ($view) {
            $environment = config('demo.environment.name');

            $view->with([
                'envName' => $environment,
                'isMain' => $environment === 'main',
                'hostname' => gethostname() ?: 'unknown',
            ]);
        });
    }
}
