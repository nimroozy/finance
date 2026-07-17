<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class EquipmentSequence extends Model
{
    protected $table = 'inventory_equipment_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
