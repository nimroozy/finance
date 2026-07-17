<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNumberBackfillHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'before_customer_number',
        'after_customer_number',
        'extracted_customer_number',
        'clean_display_name',
        'source',
        'confidence',
        'result_class',
        'dry_run',
        'applied',
        'evidence',
        'explanation',
        'actor_id',
        'run_id',
    ];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'applied' => 'boolean',
            'evidence' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
