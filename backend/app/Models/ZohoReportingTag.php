<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoReportingTag extends Model
{
    protected $fillable = [
        'zoho_tag_id', 'organization_id', 'name', 'associated_with', 'is_active',
        'raw', 'sync_status', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'raw' => 'array', 'last_synced_at' => 'datetime'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(ZohoReportingTagOption::class, 'zoho_tag_id', 'zoho_tag_id');
    }
}
