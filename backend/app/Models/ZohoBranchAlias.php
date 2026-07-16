<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZohoBranchAlias extends Model
{
    protected $fillable = ['branch_id', 'alias', 'normalized_alias'];
}
