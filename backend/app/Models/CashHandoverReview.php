<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashHandoverReview extends Model
{
    protected $fillable = ['handover_id', 'reviewer_id', 'decision', 'counted_amount', 'approved_amount', 'notes'];
    protected function casts(): array { return ['counted_amount' => 'decimal:4', 'approved_amount' => 'decimal:4']; }
}
