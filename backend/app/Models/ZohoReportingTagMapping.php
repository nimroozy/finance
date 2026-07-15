<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoReportingTagMapping extends Model
{
    protected $fillable = [
        'tag_id',
        'tag_option_id',
        'tag_name',
        'option_name',
        'branch_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
