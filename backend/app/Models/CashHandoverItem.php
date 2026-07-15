<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashHandoverItem extends Model
{
    protected $fillable = ['handover_id', 'payment_id', 'payment_amount', 'approved_amount', 'item_status', 'is_active', 'receipt_number', 'notes'];
    protected function casts(): array { return ['payment_amount' => 'decimal:4', 'approved_amount' => 'decimal:4', 'is_active' => 'boolean']; }
    public function handover(): BelongsTo { return $this->belongsTo(CashHandoverRequest::class, 'handover_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
