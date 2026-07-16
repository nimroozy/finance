<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBranchMappingConflict extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const TYPE_PREFIX_LOCATION_MISMATCH = 'prefix_location_mismatch';

    public const TYPE_PREFIX_EXISTING_MAPPING_MISMATCH = 'prefix_existing_mapping_mismatch';

    public const TYPE_MULTIPLE_INVOICE_LOCATIONS = 'multiple_invoice_locations';

    public const TYPE_DUPLICATE_PREFIX_CONFIGURATION = 'duplicate_prefix_configuration';

    public const TYPE_UNKNOWN_PREFIX = 'unknown_prefix';

    public const TYPE_ADMINISTRATOR_OVERRIDE_CONFLICT = 'administrator_override_conflict';

    protected $fillable = [
        'customer_id',
        'conflict_type',
        'status',
        'prefix_branch_id',
        'location_branch_id',
        'existing_branch_id',
        'detected_prefix',
        'customer_number',
        'evidence',
        'explanation',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prefixBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'prefix_branch_id');
    }

    public function locationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'location_branch_id');
    }
}
