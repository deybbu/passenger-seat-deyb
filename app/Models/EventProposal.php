<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventProposal extends Model
{
    protected $fillable = ['event_inquiry_id', 'version', 'snapshot', 'personal_note', 'token_hash', 'token_encrypted', 'expires_at', 'sent_at', 'accepted_at', 'superseded_at'];
    protected function casts(): array { return ['snapshot' => 'array', 'token_encrypted' => 'encrypted', 'expires_at' => 'datetime', 'sent_at' => 'datetime', 'accepted_at' => 'datetime', 'superseded_at' => 'datetime']; }
    public function inquiry(): BelongsTo { return $this->belongsTo(EventInquiry::class, 'event_inquiry_id'); }
    public function deposits(): HasMany { return $this->hasMany(EventDeposit::class); }
    public function latestDeposit(): HasOne { return $this->hasOne(EventDeposit::class)->latestOfMany(); }
}
