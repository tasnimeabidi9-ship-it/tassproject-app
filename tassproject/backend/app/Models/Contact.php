<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    public const STAGES = ['lead_found', 'approved', 'contacted', 'replied', 'interested', 'won', 'lost'];

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'website', 'domain',
        'source_url', 'match_score', 'stage', 'status', 'assigned_to', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'match_score' => 'integer',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
