<?php

namespace App\Console\Commands;

use App\Services\Zoho\ZohoOrganizationStructureSyncService;
use Illuminate\Console\Command;

class ZohoSyncOrganizationStructureCommand extends Command
{
    protected $signature = 'zoho:sync-organization-structure {--apply}';

    protected $description = 'Preview or synchronize Zoho organization structure';

    public function handle(ZohoOrganizationStructureSyncService $service): int
    {
        $stats = $service->sync((bool) $this->option('apply'));
        $this->line(json_encode(['dry_run' => ! $this->option('apply')] + $stats));

        return self::SUCCESS;
    }
}
