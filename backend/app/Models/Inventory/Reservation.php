<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\Installation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class Reservation extends Model
{
    protected $table = 'inventory_reservations';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reservation_number', 'branch_id', 'status', 'location_id', 'installation_id',
        'lead_id', 'customer_id', 'task_id', 'expires_at', 'notes', 'created_by', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class, 'reservation_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class, 'installation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
