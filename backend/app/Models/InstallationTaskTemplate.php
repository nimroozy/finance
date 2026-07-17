<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationTaskTemplate extends Model
{
    protected $fillable = ['branch_id', 'code', 'name', 'steps', 'is_active'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
