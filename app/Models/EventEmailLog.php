<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventEmailLog extends Model
{
    protected $fillable = ['event_inquiry_id', 'event_proposal_id', 'type', 'recipient', 'status', 'attachments', 'error', 'queued_at', 'sent_at'];
    protected function casts(): array { return ['attachments' => 'array', 'queued_at' => 'datetime', 'sent_at' => 'datetime']; }
    public function inquiry(): BelongsTo { return $this->belongsTo(EventInquiry::class, 'event_inquiry_id'); }
    public function proposal(): BelongsTo { return $this->belongsTo(EventProposal::class); }
}
