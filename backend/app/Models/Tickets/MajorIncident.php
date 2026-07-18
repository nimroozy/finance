<?php

namespace App\Models\Tickets;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([BelongsToBranchScope::class])]
class MajorIncident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'number',
        'title',
        'status',
        'severity',
        'description',
        'created_by',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'major_incident_ticket')
            ->withTimestamps();
    }
}
