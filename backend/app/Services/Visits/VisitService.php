<?php

namespace App\Services\Visits;

use App\Models\CollectionRouteStop;
use App\Models\CollectionVisit;
use App\Models\Customer;
use App\Models\CustomerAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Geo\HaversineDistance;
use App\Services\Notifications\NotificationService;
use App\Services\Promises\PromiseToPayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function __construct(
        protected AuditLogger $audit,
        protected HaversineDistance $geo,
        protected PromiseToPayService $promises,
        protected NotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CollectionVisit
    {
        return DB::transaction(function () use ($data, $actor) {
            $assignment = null;
            if (! empty($data['assignment_id'])) {
                $assignment = CustomerAssignment::findOrFail($data['assignment_id']);
            }

            $customer = Customer::findOrFail($data['customer_id'] ?? $assignment?->customer_id);

            $collector = $actor->collector;
            if ($actor->isCollectorOnly()) {
                if (! $collector) {
                    throw ValidationException::withMessages(['collector' => ['Collector profile required.']]);
                }
                if ($assignment && (int) $assignment->collector_id !== (int) $collector->id) {
                    throw ValidationException::withMessages(['assignment_id' => ['Not your assignment.']]);
                }
            } else {
                $collector = $collector ?? ($assignment?->collector);
                if (! $collector && ! empty($data['collector_id'])) {
                    $collector = \App\Models\Collector::findOrFail($data['collector_id']);
                }
            }

            if (! $collector) {
                throw ValidationException::withMessages(['collector_id' => ['Collector is required.']]);
            }

            $outcome = $data['outcome'];
            if (! in_array($outcome, CollectionVisit::outcomeCodes(), true)) {
                throw ValidationException::withMessages(['outcome' => ['Invalid visit outcome.']]);
            }

            $notes = trim((string) ($data['notes'] ?? ''));
            if (in_array($outcome, CollectionVisit::NOTE_REQUIRED_OUTCOMES, true) && $notes === '') {
                throw ValidationException::withMessages(['notes' => ['A note is required for this outcome.']]);
            }

            $followUpRequired = (bool) ($data['follow_up_required'] ?? ($outcome === CollectionVisit::OUTCOME_FOLLOW_UP));
            if ($followUpRequired && empty($data['follow_up_date'])) {
                throw ValidationException::withMessages(['follow_up_date' => ['Follow-up date is required.']]);
            }

            if ($outcome === CollectionVisit::OUTCOME_PROMISE_TO_PAY && empty($data['promise'])) {
                throw ValidationException::withMessages(['promise' => ['Promise payload is required for promise_to_pay outcome.']]);
            }

            $gpsPermission = $data['gps_permission_state'] ?? null;
            $lat = array_key_exists('latitude', $data) && $data['latitude'] !== null
                ? (float) $data['latitude']
                : null;
            $lon = array_key_exists('longitude', $data) && $data['longitude'] !== null
                ? (float) $data['longitude']
                : null;

            if ($gpsPermission === 'denied') {
                $lat = null;
                $lon = null;
            }

            $custLat = $customer->latitude !== null ? (float) $customer->latitude : null;
            $custLon = $customer->longitude !== null ? (float) $customer->longitude : null;

            $distance = null;
            $risk = null;
            if ($lat !== null && $lon !== null && $custLat !== null && $custLon !== null) {
                $distance = $this->geo->meters($lat, $lon, $custLat, $custLon);
                $risk = $this->geo->riskLevel($distance);
            }

            $escalation = $outcome === CollectionVisit::OUTCOME_REQUESTED_MANAGER
                || $outcome === CollectionVisit::OUTCOME_DISPUTED_BALANCE
                || (bool) ($data['escalation_flag'] ?? false);

            $visit = CollectionVisit::create([
                'branch_id' => $customer->branch_id,
                'customer_id' => $customer->id,
                'assignment_id' => $assignment?->id,
                'collector_id' => $collector->id,
                'route_stop_id' => $data['route_stop_id'] ?? null,
                'recorded_by' => $actor->id,
                'visited_at' => $data['visited_at'] ?? now(),
                'outcome' => $outcome,
                'notes' => $notes !== '' ? $notes : null,
                'contact_status' => $data['contact_status'] ?? null,
                'person_met' => $data['person_met'] ?? null,
                'follow_up_required' => $followUpRequired,
                'follow_up_date' => $data['follow_up_date'] ?? null,
                'escalation_flag' => $escalation,
                'origin' => $data['origin'] ?? 'online',
                'signature_placeholder' => (bool) ($data['signature_placeholder'] ?? false),
                'latitude' => $lat,
                'longitude' => $lon,
                'gps_accuracy' => $data['gps_accuracy'] ?? null,
                'gps_permission_state' => $gpsPermission ?? ($lat !== null ? 'granted' : 'unavailable'),
                'gps_source' => $data['gps_source'] ?? 'browser',
                'gps_captured_at' => $data['gps_captured_at'] ?? ($lat !== null ? now() : null),
                'device_info' => $data['device_info'] ?? null,
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'customer_latitude' => $custLat,
                'customer_longitude' => $custLon,
                'distance_meters' => $distance,
                'gps_risk_level' => $risk,
            ]);

            if ($assignment && $assignment->is_active) {
                $assignmentUpdates = [
                    'last_visit_at' => $visit->visited_at,
                ];
                if ($assignment->status === CustomerAssignment::STATUS_ASSIGNED
                    || $assignment->status === CustomerAssignment::STATUS_ACCEPTED) {
                    $assignmentUpdates['status'] = CustomerAssignment::STATUS_IN_PROGRESS;
                }
                $assignment->update($assignmentUpdates);
            }

            if (! empty($data['route_stop_id'])) {
                CollectionRouteStop::where('id', $data['route_stop_id'])->update([
                    'status' => CollectionRouteStop::STATUS_COMPLETED,
                    'visit_id' => $visit->id,
                    'completed_at' => now(),
                ]);
            }

            if ($outcome === CollectionVisit::OUTCOME_PROMISE_TO_PAY) {
                $promiseData = $data['promise'];
                $promiseData['customer_id'] = $customer->id;
                $promiseData['assignment_id'] = $assignment?->id;
                $promiseData['visit_id'] = $visit->id;
                $promiseData['collector_id'] = $collector->id;
                $promiseData['branch_id'] = $customer->branch_id;
                $this->promises->create($promiseData, $actor);
            }

            if ($escalation) {
                $this->notifications->notifyBranchManagers(
                    (int) $customer->branch_id,
                    'visit.escalation',
                    'Visit escalation',
                    'Collector escalated customer '.$customer->contact_name,
                    ['visit_id' => $visit->id, 'customer_id' => $customer->id, 'outcome' => $outcome]
                );
            }

            $this->audit->log('visit.created', $visit, null, $visit->toArray(), $visit->branch_id);

            return $visit->fresh(['customer', 'collector.user', 'promise', 'files']);
        });
    }

    public function addCorrectionNote(CollectionVisit $visit, string $note, User $actor): CollectionVisit
    {
        $visit->update([
            'correction_note' => $note,
            'correction_by' => $actor->id,
            'correction_at' => now(),
        ]);

        $this->audit->log('visit.correction', $visit, null, ['correction_note' => $note], $visit->branch_id);

        return $visit->fresh();
    }
}
