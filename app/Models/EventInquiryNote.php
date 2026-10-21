<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInquiryNote extends Model
{
    protected $fillable = ['event_inquiry_id', 'created_by', 'note'];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(EventInquiry::class, 'event_inquiry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
