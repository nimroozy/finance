<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashHandoverVariance extends Model
{
    protected $fillable = ['handover_id', 'expected_amount', 'actual_amount', 'variance_amount', 'variance_type', 'status', 'notes', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['expected_amount' => 'decimal:4', 'actual_amount' => 'decimal:4', 'variance_amount' => 'decimal:4', 'resolved_at' => 'datetime']; }
}
