<?php

namespace App\GraphQL\Queries;

use App\Models\Tool;

class AvailableAgents
{
    public function __invoke($_, array $args): array
    {
        return Tool::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get(['key', 'name', 'description'])
            ->map(fn (Tool $tool) => [
                'key' => $tool->key,
                'name' => $tool->name,
                'description' => $tool->description,
            ])
            ->all();
    }
}
