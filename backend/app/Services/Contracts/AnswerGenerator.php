<?php

namespace App\Services\Contracts;

use App\DTOs\AnswerResult;

interface AnswerGenerator
{
    /**
     * @param string $question
     * @param array $contextChunks
     * @return AnswerResult
     */
    public function generate(string $question, array $contextChunks): AnswerResult;
}
