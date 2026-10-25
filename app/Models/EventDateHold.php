<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDateHold extends Model
{
    protected $fillable = ['event_date', 'event_inquiry_id', 'event_proposal_id', 'status', 'expires_at', 'released_at', 'release_reason'];
    protected function casts(): array { return ['event_date' => 'date', 'expires_at' => 'datetime', 'released_at' => 'datetime']; }
    public function inquiry(): BelongsTo { return $this->belongsTo(EventInquiry::class, 'event_inquiry_id'); }
    public function proposal(): BelongsTo { return $this->belongsTo(EventProposal::class); }
}
