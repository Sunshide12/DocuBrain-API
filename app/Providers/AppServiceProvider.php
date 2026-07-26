<?php

namespace App\Providers;

use App\Events\DocumentProcessed;
use App\Events\DocumentUploaded;
use App\Listeners\InvalidateDocumentCacheOnCompletion;
use App\Listeners\InvalidateDocumentsCache;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\Contracts\MathExtractor;
use App\Services\Contracts\OpenRouterClient;
use App\Services\Contracts\TextExtractor;
use App\Services\OpenRouterEmbeddingProvider;
use App\Services\OpenRouterHttpClient;
use App\Services\PdfTextExtractor;
use App\Services\PdfTextMathExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TextExtractor::class,
            PdfTextExtractor::class
        );
        $this->app->bind(
            EmbeddingProvider::class,
            OpenRouterEmbeddingProvider::class
        );
        $this->app->bind(
            MathExtractor::class,
            PdfTextMathExtractor::class
        );
        $this->app->bind(
            OpenRouterClient::class,
            OpenRouterHttpClient::class
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
        Event::listen(DocumentProcessed::class, InvalidateDocumentCacheOnCompletion::class);
    }
}
