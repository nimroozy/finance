<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BranchScope::class])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name_en',
        'name_fa',
        'province_en',
        'province_fa',
        'phone',
        'address',
        'receipt_prefix',
        'is_active',
        'is_headquarter',
        'exclude_from_field_collection',
        'zoho_location_id',
        'zoho_reporting_tag_id',
        'zoho_reporting_tag_option_id',
        'mapping_type',
        'zoho_sync_status',
        'last_structure_sync_at',
        'local_override',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_headquarter' => 'boolean',
            'exclude_from_field_collection' => 'boolean',
            'last_structure_sync_at' => 'datetime',
            'local_override' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @param  Builder<Branch>  $query
     * @return Builder<Branch>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
