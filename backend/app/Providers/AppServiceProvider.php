<?php

namespace App\Providers;

use App\Events\DocumentUploaded;
use App\Listeners\InvalidateDocumentsCache;
use Illuminate\Support\Facades\Event;
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
     *
     * WHY Event::listen() here instead of EventServiceProvider?
     * Laravel 11 removed the standalone EventServiceProvider by default.
     * The recommended place for event registration is now AppServiceProvider.
     */
    public function boot(): void
    {
        // Invalidate the documents cache whenever a new document is uploaded.
        Event::listen(DocumentUploaded::class, InvalidateDocumentsCache::class);
    }
}
