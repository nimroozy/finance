<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class CustomerNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'author_id',
        'assignment_id',
        'body',
        'edit_history',
    ];

    protected function casts(): array
    {
        return [
            'edit_history' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(CustomerAssignment::class, 'assignment_id');
    }
}
