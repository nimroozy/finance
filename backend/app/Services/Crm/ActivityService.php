<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivityService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Lead $lead, array $data, ?User $actor = null): Activity
    {
        $type = $data['type'] ?? null;
        if (! in_array($type, Activity::TYPES, true)) {
            throw new InvalidArgumentException("Invalid activity type [{$type}].");
        }

        return DB::transaction(function () use ($lead, $data, $actor, $type) {
            $activity = Activity::query()->create([
                'lead_id' => $lead->id,
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'branch_id' => $lead->branch_id,
                'type' => $type,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
                'performed_by' => $data['performed_by'] ?? $actor?->id,
                'performed_at' => $data['performed_at'] ?? now(),
                'customer_visible' => (bool) ($data['customer_visible'] ?? false),
                'meta' => $data['meta'] ?? null,
            ]);

            if (! empty($data['next_follow_up_at'])) {
                $lead->next_follow_up_at = $data['next_follow_up_at'];
                $lead->save();
            }

            $this->audit->log('crm.activity.created', $activity, null, $activity->toArray(), $lead->branch_id);

            return $activity->fresh();
        });
    }
}
