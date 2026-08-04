<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadQueue extends Model
{
    protected $table = 'leads_queue';

    public const STATUSES = ['pending_review', 'approved', 'rejected'];

    protected $fillable = [
        'profile_id', 'raw_name', 'domain', 'email', 'confidence_score', 'match_score',
        'source_url', 'approval_status', 'approved_by', 'found_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'match_score' => 'integer',
            'found_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class, 'profile_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
