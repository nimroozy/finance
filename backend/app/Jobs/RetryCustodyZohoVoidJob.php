<?php

namespace App\Jobs;

use App\Models\CustodyConflict;
use App\Services\Cash\CustodyAwareReversalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryCustodyZohoVoidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $custodyConflictId)
    {
        $this->onQueue('zoho');
    }

    public function handle(CustodyAwareReversalService $custody): void
    {
        $conflict = CustodyConflict::withoutGlobalScopes()->find($this->custodyConflictId);
        if (! $conflict) {
            return;
        }
        if ($conflict->zoho_void_status === CustodyAwareReversalService::ZOHO_VOIDED
            || $conflict->zoho_void_status === CustodyAwareReversalService::ZOHO_NOT_REQUIRED) {
            return;
        }
        if ($conflict->status !== CustodyAwareReversalService::STATUS_APPROVED) {
            return;
        }

        $custody->retryZohoVoid($conflict, forceLiveZoho: true);
    }
}
