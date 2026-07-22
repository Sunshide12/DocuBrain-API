<?php

namespace App\GraphQL\Queries;

use App\Models\Quiz;
use Illuminate\Support\Facades\Auth;

final class DocumentQuizzes
{
    public function __invoke($_, array $args): \Illuminate\Database\Eloquent\Collection
    {
        return Quiz::with('questions')
            ->where('document_id', $args['document_id'])
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();
    }
}
