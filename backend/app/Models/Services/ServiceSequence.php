<?php

namespace App\Models\Services;

use Illuminate\Database\Eloquent\Model;

class ServiceSequence extends Model
{
    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
