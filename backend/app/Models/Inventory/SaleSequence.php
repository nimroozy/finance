<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class SaleSequence extends Model
{
    protected $table = 'inventory_sale_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
