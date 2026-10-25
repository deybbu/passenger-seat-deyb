<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnansweredQuestion extends Model
{
    protected $fillable = ['question', 'status', 'faq_id', 'answered_at'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class);
    }
}
