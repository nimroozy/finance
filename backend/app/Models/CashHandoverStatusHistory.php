<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashHandoverStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'cash_handover_status_history';

    protected $fillable = ['handover_id', 'from_status', 'to_status', 'changed_by', 'notes', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
