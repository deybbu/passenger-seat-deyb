<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDeposit extends Model
{
    protected $fillable = ['event_proposal_id', 'amount', 'paid_at', 'method', 'status', 'proof_path', 'reviewed_by', 'reviewed_at', 'review_note', 'override_reason'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'paid_at' => 'date', 'reviewed_at' => 'datetime']; }
    public function proposal(): BelongsTo { return $this->belongsTo(EventProposal::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
