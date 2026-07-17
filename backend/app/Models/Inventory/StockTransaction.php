<?php

namespace App\Models\Inventory;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[ScopedBy([BelongsToBranchScope::class])]
class StockTransaction extends Model
{
    public $timestamps = false;

    protected $table = 'inventory_stock_transactions';

    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_TRANSFER_DISPATCH = 'transfer_dispatch';
    public const TYPE_TRANSFER_RECEIVE = 'transfer_receive';
    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_RELEASE = 'release';
    public const TYPE_SALE = 'sale';
    public const TYPE_INSTALLATION = 'installation';
    public const TYPE_RETURN = 'return';
    public const TYPE_REPAIR = 'repair';
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_LOSS = 'loss';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_SCRAP = 'scrap';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_CUSTODY = 'custody';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Stock transactions cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Stock transactions cannot be deleted.');
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Stock transactions cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Stock transactions cannot be deleted.');
        });
    }
}
