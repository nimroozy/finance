<?php

namespace App\Console\Commands;

use App\Services\Ownership\TemporaryAssignmentService;
use Illuminate\Console\Command;

class ExpireTemporaryAssignmentsCommand extends Command
{
    protected $signature = 'assignments:expire-temporary {--date=}';
    protected $description = 'Expire temporary collection assignments and restore permanent ownership responsibility';

    public function handle(TemporaryAssignmentService $service): int
    {
        $count = $service->expireDue($this->option('date'));
        $this->info("Expired {$count} temporary assignment(s).");

        return self::SUCCESS;
    }
}
