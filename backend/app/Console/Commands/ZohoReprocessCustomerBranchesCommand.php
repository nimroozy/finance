<?php

namespace App\Console\Commands;

use App\Services\Zoho\ZohoBranchReprocessService;
use Illuminate\Console\Command;

class ZohoReprocessCustomerBranchesCommand extends Command
{
    protected $signature = 'zoho:reprocess-customer-branches {--apply} {--branch=} {--customer=} {--limit=500}';

    protected $description = 'Preview or apply customer branch mapping changes';

    public function handle(ZohoBranchReprocessService $service): int
    {
        $stats = $service->customers(
            (bool) $this->option('apply'),
            $this->option('branch') !== null ? (int) $this->option('branch') : null,
            $this->option('customer') !== null ? (int) $this->option('customer') : null,
            (int) $this->option('limit'),
        );
        $this->line(json_encode(['dry_run' => ! $this->option('apply')] + $stats));

        return self::SUCCESS;
    }
}
