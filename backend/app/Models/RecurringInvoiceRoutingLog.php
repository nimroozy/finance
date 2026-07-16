<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringInvoiceRoutingLog extends Model
{
    protected $fillable = [
        'customer_id', 'invoice_id', 'collector_id', 'ownership_source', 'result', 'details',
    ];
    protected function casts(): array { return ['details' => 'array']; }
}
