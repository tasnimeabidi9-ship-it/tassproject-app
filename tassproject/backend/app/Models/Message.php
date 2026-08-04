<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'contact_id', 'campaign_id', 'channel', 'direction', 'subject',
        'body', 'status', 'opened_at', 'clicked_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }
}
