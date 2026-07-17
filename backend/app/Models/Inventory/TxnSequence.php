<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class TxnSequence extends Model
{
    protected $table = 'inventory_txn_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
