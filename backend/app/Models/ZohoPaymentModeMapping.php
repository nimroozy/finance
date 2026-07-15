<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoPaymentModeMapping extends Model
{
    protected $fillable = [
        'zoho_payment_mode_id',
        'name',
        'local_method',
    ];
}
