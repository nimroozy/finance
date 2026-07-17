<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([BelongsToBranchScope::class])]
class Activity extends Model
{
    protected $table = 'crm_activities';

    public const TYPES = [
        'call',
        'whatsapp',
        'sms',
        'email',
        'meeting',
        'visit',
        'site_survey',
        'demo',
        'follow_up',
        'internal_discussion',
    ];

    protected $fillable = [
        'lead_id',
        'opportunity_id',
        'branch_id',
        'type',
        'subject',
        'body',
        'performed_by',
        'performed_at',
        'customer_visible',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'customer_visible' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
