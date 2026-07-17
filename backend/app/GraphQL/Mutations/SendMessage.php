<?php

namespace App\GraphQL\Mutations;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Contracts\AnswerGenerator;
use App\Services\Contracts\EmbeddingProvider;
use App\Services\PgvectorSimilaritySearch;
use Illuminate\Support\Facades\Auth;

class SendMessage
{
    public function __construct(
        private readonly EmbeddingProvider $embeddingProvider,
        private readonly PgvectorSimilaritySearch $similaritySearch,
        private readonly AnswerGenerator $answerGenerator
    ) {}

    public function __invoke($_, array $args): Message
    {
        $conversation = Conversation::findOrFail($args['conversation_id']);
        $user = Auth::user();

        // 1. Guardar mensaje del usuario
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $args['content'],
        ]);

        // 2. Embedding de la pregunta
        $questionVector = $this->embeddingProvider->embed($args['content']);

        // 3. Similarity search
        $threshold = (float) config('services.openrouter.similarity_threshold', 0.75);
        $chunks = $this->similaritySearch->search(
            queryVector: $questionVector,
            userId: $user->id,
            documentId: $conversation->document_id,
            threshold: $threshold
        );

        // 4. Generar respuesta
        $result = $this->answerGenerator->generate($args['content'], $chunks->all());

        // 5. Guardar respuesta
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result->answer,
            'source_chunk_ids' => $result->sourceChunks, // Guardamos la estructura rica [{'id':1, 'page_number':2}]
        ]);

        return $assistantMessage;
    }
}
