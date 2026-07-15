<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashHandoverSequence extends Model
{
    protected $fillable = ['branch_id', 'year', 'next_number'];
}
