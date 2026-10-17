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
        $this->app->bind(
            \App\Services\GoogleSheets\SheetsGateway::class,
            \App\Services\GoogleSheets\GoogleSheetsGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\FormSubmissionCreated::class,
            \App\Listeners\QueueSheetSync::class
        );

        \App\Models\FormField::observe(
            \App\Observers\FormFieldObserver::class
        );
    }
}
