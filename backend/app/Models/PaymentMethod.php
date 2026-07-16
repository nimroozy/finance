<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    public const CODE_CASH = 'cash';

    public const CODE_BANK_TRANSFER = 'bank_transfer';

    public const CODE_CARD = 'card';

    public const CODE_MOBILE_MONEY = 'mobile_money';

    public const CODE_CHEQUE = 'cheque';

    public const CODE_OTHER = 'other';

    protected $fillable = [
        'code',
        'name_en',
        'name_fa',
        'is_active',
        'collector_allowed',
        'requires_reference',
        'requires_evidence',
        'affects_cash_wallet',
        'receipt_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'collector_allowed' => 'boolean',
            'requires_reference' => 'boolean',
            'requires_evidence' => 'boolean',
            'affects_cash_wallet' => 'boolean',
            'receipt_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
