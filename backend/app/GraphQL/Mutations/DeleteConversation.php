<?php

namespace App\GraphQL\Mutations;

use App\Models\Conversation;

class DeleteConversation
{
    public function __invoke($_, array $args): bool
    {
        $conversation = Conversation::findOrFail($args['id']);
        return $conversation->delete();
    }
}
