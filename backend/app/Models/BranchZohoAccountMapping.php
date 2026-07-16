<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchZohoAccountMapping extends Model
{
    protected $fillable = [
        'branch_id', 'zoho_location_id', 'zoho_account_id', 'zoho_account_name',
        'zoho_payment_mode_id', 'zoho_payment_mode_name', 'currency', 'receipt_prefix',
        'is_active', 'readiness_status', 'validation_status', 'last_validated_at',
        'validation_message', 'mapping_version', 'updated_by',
    ];
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_validated_at' => 'datetime'];
    }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function isReady(): bool { return $this->is_active && $this->readiness_status === 'ready'; }
}
