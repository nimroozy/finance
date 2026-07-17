<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class AssetSequence extends Model
{
    protected $table = 'inventory_asset_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
