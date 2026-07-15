<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoBranchMapping extends Model
{
    public const METHOD_ZOHO_BRANCH = 'zoho_branch';

    public const METHOD_ZOHO_LOCATION = 'zoho_location';

    public const METHOD_REPORTING_TAG = 'reporting_tag';

    public const METHOD_CUSTOM_FIELD = 'custom_field';

    protected $fillable = [
        'branch_id',
        'mapping_method',
        'zoho_value',
        'zoho_label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
