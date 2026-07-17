<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class TransferSequence extends Model
{
    protected $table = 'inventory_transfer_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
