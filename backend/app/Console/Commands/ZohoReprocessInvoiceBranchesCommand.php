<?php

namespace App\Console\Commands;

use App\Services\Zoho\ZohoBranchReprocessService;
use Illuminate\Console\Command;

class ZohoReprocessInvoiceBranchesCommand extends Command
{
    protected $signature = 'zoho:reprocess-invoice-branches {--apply} {--branch=} {--customer=} {--limit=500}';

    protected $description = 'Preview or apply invoice branch mapping changes';

    public function handle(ZohoBranchReprocessService $service): int
    {
        $stats = $service->invoices(
            (bool) $this->option('apply'),
            $this->option('branch') !== null ? (int) $this->option('branch') : null,
            $this->option('customer') !== null ? (int) $this->option('customer') : null,
            (int) $this->option('limit'),
        );
        $this->line(json_encode(['dry_run' => ! $this->option('apply')] + $stats));

        return self::SUCCESS;
    }
}
