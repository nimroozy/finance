<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class Organization extends Model
{
    use SoftDeletes;

    protected $table = 'crm_organizations';

    protected $fillable = [
        'branch_id',
        'name',
        'phone',
        'email',
        'address',
        'type',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'organization_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'organization_id');
    }
}
