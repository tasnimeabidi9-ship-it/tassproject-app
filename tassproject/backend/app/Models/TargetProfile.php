<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetProfile extends Model
{
    protected $fillable = ['name', 'industry', 'job_title', 'location', 'keywords', 'created_by'];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class, 'profile_id');
    }
}
