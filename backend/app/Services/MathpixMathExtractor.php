<?php

namespace App\Services;

use App\Services\Contracts\MathExtractor;
use Illuminate\Support\Facades\Http;

class MathpixMathExtractor implements MathExtractor
{
    public function extract(string $absolutePath): array
    {
        $apiUrl = rtrim(config('services.math_extraction.api_url'), '/');
        $appId  = config('services.math_extraction.app_id');
        $appKey = config('services.math_extraction.api_key');

        // Upload PDF and request MMD (Mathpix Markdown) conversion
        $uploadResponse = Http::withHeaders([
            'app_id'  => $appId,
            'app_key' => $appKey,
        ])->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post("{$apiUrl}/pdf");

        if ($uploadResponse->failed()) {
            throw new \Exception("Mathpix PDF upload failed: " . $uploadResponse->status() . " " . $uploadResponse->body());
        }

        $pdfId = $uploadResponse->json('pdf_id');

        if (!$pdfId) {
            throw new \Exception("Mathpix did not return a pdf_id: " . $uploadResponse->body());
        }

        // Poll until conversion is complete
        $maxAttempts = 30;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = Http::withHeaders([
                'app_id'  => $appId,
                'app_key' => $appKey,
            ])->get("{$apiUrl}/pdf/{$pdfId}");

            $status = $statusResponse->json('status');

            if ($status === 'completed') {
                break;
            }

            if ($status === 'error') {
                throw new \Exception("Mathpix conversion error for pdf_id={$pdfId}: " . $statusResponse->body());
            }

            sleep(2);
        }

        if ($status !== 'completed') {
            throw new \Exception("Mathpix conversion timed out for pdf_id={$pdfId}.");
        }

        // Fetch the MMD output (one page at a time via lines endpoint)
        $mmdResponse = Http::withHeaders([
            'app_id'  => $appId,
            'app_key' => $appKey,
        ])->get("{$apiUrl}/pdf/{$pdfId}.mmd");

        if ($mmdResponse->failed()) {
            throw new \Exception("Mathpix MMD fetch failed: " . $mmdResponse->status());
        }

        // The MMD format uses page breaks (\n\n---\n\n or similar).
        // Split by Mathpix's page separator and map to page numbers.
        $mmdContent = $mmdResponse->body();
        $rawPages   = preg_split('/\n{2,}---\n{2,}/', $mmdContent);

        $pages = [];
        foreach ($rawPages as $index => $pageContent) {
            $content = trim($pageContent);
            if ($content !== '') {
                $pages[$index + 1] = $content;
            }
        }

        return $pages;
    }
}
