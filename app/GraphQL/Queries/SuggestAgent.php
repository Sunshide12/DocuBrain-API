<?php

namespace App\GraphQL\Queries;

use App\Models\Document;

final class SuggestAgent
{
    public function __invoke($_, array $args): array
    {
        $document = Document::findOrFail($args['document_id']);

        $sampleChunks = $document->chunks()->limit(10)->pluck('content')->join(' ');
        $wordCount    = str_word_count($sampleChunks);

        $mathPatterns = preg_match_all('/[=∫∑∏√±×÷]|\d+\s*[+\-*\/^]\s*\d+/', $sampleChunks);
        $mathDensity  = $wordCount > 0 ? $mathPatterns / $wordCount : 0;

        if ($mathDensity > 0.02) {
            return [
                'agent_type' => 'math_solver',
                'confidence' => min($mathDensity * 10, 1.0),
            ];
        }

        if ($wordCount > 400) {
            return [
                'agent_type' => 'quiz_generator',
                'confidence' => 0.6,
            ];
        }

        return [
            'agent_type' => 'document_qa',
            'confidence' => 1.0,
        ];
    }
}
