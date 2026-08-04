<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sequence extends Model
{
    protected $fillable = ['name', 'steps'];

    protected function casts(): array
    {
        return [
            'steps' => 'array', // [{ channel, delay_days, subject, body }]
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
