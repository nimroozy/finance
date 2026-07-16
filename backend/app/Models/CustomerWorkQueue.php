<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWorkQueue extends Model
{
    protected $table = 'customer_work_queues';
    protected $fillable = [
        'customer_id', 'branch_id', 'effective_collector_id', 'ownership_source',
        'total_open_balance', 'open_invoice_count', 'oldest_due_date', 'days_overdue',
        'last_payment_date', 'last_visit_at', 'promise_status', 'priority', 'work_status', 'last_routed_at',
    ];
    protected function casts(): array
    {
        return [
            'total_open_balance' => 'decimal:4',
            'oldest_due_date' => 'date',
            'last_payment_date' => 'date',
            'last_visit_at' => 'datetime',
            'last_routed_at' => 'datetime',
        ];
    }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function collector(): BelongsTo { return $this->belongsTo(Collector::class, 'effective_collector_id'); }
}
