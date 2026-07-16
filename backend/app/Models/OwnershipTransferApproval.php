<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnershipTransferApproval extends Model
{
    protected $fillable = ['transfer_request_id', 'approved_by', 'decision', 'notes'];
}
