<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoOrganization extends Model
{
    protected $fillable = [
        'zoho_connection_id',
        'zoho_org_id',
        'name',
        'currency_code',
        'is_selected',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'raw' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZohoConnection::class, 'zoho_connection_id');
    }
}
