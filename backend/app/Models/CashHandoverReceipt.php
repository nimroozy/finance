<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashHandoverReceipt extends Model
{
    public $timestamps = false;
    protected $fillable = ['handover_id', 'receipt_number', 'amount', 'currency', 'issued_by', 'issued_at'];
    protected function casts(): array { return ['amount' => 'decimal:4', 'issued_at' => 'datetime']; }
}
