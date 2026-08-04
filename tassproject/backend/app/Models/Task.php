<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'contact_id', 'assigned_to', 'title', 'description',
        'due_date', 'status', 'created_by_ai',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'created_by_ai' => 'boolean',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
