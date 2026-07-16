<?php

namespace App\Jobs;

use App\Models\AssignmentBulkOperation;
use App\Models\User;
use App\Services\Assignments\AssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessBulkAssignmentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $operationId,
        public int $actorId,
    ) {}

    public function handle(AssignmentService $assignments): void
    {
        $operation = AssignmentBulkOperation::withoutGlobalScopes()->find($this->operationId);
        $actor = User::find($this->actorId);

        if (! $operation || ! $actor) {
            return;
        }

        try {
            $assignments->executeBulk($operation, $actor);
        } catch (\Throwable $e) {
            $operation->update([
                'status' => AssignmentBulkOperation::STATUS_FAILED,
                'result_payload' => ['error' => $e->getMessage()],
            ]);
            Log::error('Bulk assignment failed', ['operation_id' => $this->operationId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
