<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ReservationSequence extends Model
{
    protected $table = 'inventory_reservation_sequences';

    protected $fillable = ['branch_id', 'year', 'last_sequence'];
}
