<?php

namespace App\Providers;

use App\Events\DocumentUploaded;
use App\Listeners\InvalidateDocumentsCache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Contracts\TextExtractor::class,
            \App\Services\PdfTextExtractor::class
        );
        $this->app->bind(
            \App\Services\Contracts\EmbeddingProvider::class,
            \App\Services\OpenRouterEmbeddingProvider::class
        );
        $this->app->bind(
            \App\Services\Contracts\MathExtractor::class,
            \App\Services\PdfTextMathExtractor::class
        );
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
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('guest', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Invalidate the documents cache whenever a new document is uploaded.
        Event::listen(DocumentUploaded::class, InvalidateDocumentsCache::class);

        // Invalidate the documents cache whenever a document finishes processing.
        Event::listen(\App\Events\DocumentProcessed::class, \App\Listeners\InvalidateDocumentCacheOnCompletion::class);

        // Auto-generate a quiz when a document finishes processing.
        Event::listen(\App\Events\DocumentProcessed::class, function (\App\Events\DocumentProcessed $event) {
            try {
                \App\Jobs\GenerateAutoQuizJob::dispatch($event->document);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to dispatch GenerateAutoQuizJob: ' . $e->getMessage());
            }
        });
    }
}
