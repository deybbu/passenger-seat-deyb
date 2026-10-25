<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAlertRule extends Model
{
    protected $fillable = ['rule_key', 'trigger', 'action', 'is_enabled', 'configuration'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'configuration' => 'array',
        ];
    }
}
