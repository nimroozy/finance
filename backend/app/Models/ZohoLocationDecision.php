<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoLocationDecision extends Model
{
    protected $fillable = [
        'zoho_location_id',
        'decision',
        'branch_id',
        'decided_by',
        'notes',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
