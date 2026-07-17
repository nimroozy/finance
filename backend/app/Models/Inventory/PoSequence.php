<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class PoSequence extends Model
{
    protected $table = 'inventory_po_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
