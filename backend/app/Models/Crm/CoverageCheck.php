<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverageCheck extends Model
{
    protected $table = 'crm_coverage_checks';

    public const RESULTS = [
        'covered',
        'partially_covered',
        'survey_required',
        'tower_expansion_required',
        'fiber_required',
        'cannot_serve',
    ];

    protected $fillable = [
        'lead_id',
        'result',
        'candidate_tower',
        'distance_m',
        'notes',
        'engineer_id',
        'signal_estimate',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'distance_m' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }
}
