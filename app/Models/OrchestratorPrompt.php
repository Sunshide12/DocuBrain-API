<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrchestratorPrompt extends Model
{
    protected $fillable = ['key', 'content', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
