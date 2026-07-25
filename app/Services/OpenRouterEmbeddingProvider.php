<?php

namespace App\Services;

use App\Services\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class OpenRouterEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @param string $text
     * @return array<float>
     */
    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    /**
     * @param string[] $texts
     * @return array<int, array<float>>
     */
    public function embedBatch(array $texts): array
    {
        $allEmbeddings = [];
        // Dividir en lotes de 100 textos por llamada
        $chunks = array_chunk($texts, 100);

        $baseUrl = config('services.openrouter.base_url');
        $apiKey = config('services.openrouter.api_key');
        $model = config('services.openrouter.embedding_model');

        foreach ($chunks as $batch) {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/') . '/embeddings', [
                    'model' => $model,
                    'input' => $batch,
                ]);

            if ($response->failed()) {
                // Si es 429 u otro error HTTP, lanzar excepción con body
                throw new \Exception("Error OpenRouter API Embeddings: " . $response->status() . " - " . $response->body());
            }

            $data = $response->json('data');
            if (!is_array($data)) {
                throw new \Exception("Estructura de respuesta inesperada en OpenRouter Embeddings: " . $response->body());
            }

            // Los resultados pueden no venir ordenados, usamos el index devuelto
            $batchEmbeddings = [];
            foreach ($data as $item) {
                $batchEmbeddings[$item['index']] = $item['embedding'];
            }
            
            // Ordenar por index por si la API los devuelve en desorden
            ksort($batchEmbeddings);
            
            $allEmbeddings = array_merge($allEmbeddings, array_values($batchEmbeddings));
        }

        return $allEmbeddings;
    }
}
