<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPaymentConfiguration extends Model
{
    protected $fillable = [
        'branch_id', 'account_mapping_id', 'require_ready_for_live',
        'allow_dry_run_without_mapping', 'settings',
    ];
    protected function casts(): array
    {
        return [
            'require_ready_for_live' => 'boolean',
            'allow_dry_run_without_mapping' => 'boolean',
            'settings' => 'array',
        ];
    }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function mapping(): BelongsTo { return $this->belongsTo(BranchZohoAccountMapping::class, 'account_mapping_id'); }
}
