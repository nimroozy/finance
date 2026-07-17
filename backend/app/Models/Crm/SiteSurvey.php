<?php

namespace App\Models\Crm;

use App\Models\Branch;
use App\Models\Scopes\BelongsToBranchScope;
use App\Models\Tickets\OperationalAttachment;
use App\Models\Tickets\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[ScopedBy([BelongsToBranchScope::class])]
class SiteSurvey extends Model
{
    protected $table = 'crm_site_surveys';

    protected $fillable = [
        'lead_id',
        'branch_id',
        'gps_lat',
        'gps_lng',
        'los_status',
        'signal_estimate',
        'mounting_location',
        'power_availability',
        'equipment_recommendation',
        'technician_id',
        'survey_date',
        'customer_approved',
        'notes',
        'task_id',
    ];

    protected function casts(): array
    {
        return [
            'gps_lat' => 'decimal:7',
            'gps_lng' => 'decimal:7',
            'survey_date' => 'date',
            'customer_approved' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(OperationalAttachment::class, 'attachable');
    }
}
