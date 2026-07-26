<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $fillable = ['key', 'name', 'description', 'is_enabled', 'sort_order'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
