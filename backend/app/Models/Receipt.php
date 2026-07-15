<?php

namespace App\Models;

use App\Models\Scopes\BelongsToBranchScope;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([BelongsToBranchScope::class])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'uuid',
        'receipt_number',
        'payment_id',
        'branch_id',
        'verification_token',
        'status',
        'reprint_count',
        'pdf_path',
        'html_path',
        'language',
        'template_version',
        'issued_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'reprint_count' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(ReceiptPrintLog::class);
    }
}
