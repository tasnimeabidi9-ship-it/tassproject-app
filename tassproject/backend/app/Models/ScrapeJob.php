<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapeJob extends Model
{
    protected $fillable = [
        'profile_id', 'queries_run', 'leads_found', 'emails_enriched',
        'status', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class, 'profile_id');
    }
}
