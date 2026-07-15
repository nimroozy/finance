<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromiseToPayStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'promise_to_pay_status_history';

    protected $fillable = [
        'promise_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function promise(): BelongsTo
    {
        return $this->belongsTo(PromiseToPay::class, 'promise_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
