<?php

namespace App\Console\Commands;

use App\Support\Acceptance\AcceptanceDbAssertions;
use Illuminate\Console\Command;

class AcceptanceAssertCommand extends Command
{
    protected $signature = 'acceptance:assert
        {--entity= : customer|lead|service|user|audit|ticket|task|payment|assignment|product}
        {--key= : column}
        {--value= : value}
        {--expect=* : field=value expectations}
        {--stock : run stock reconciliation}';

    protected $description = 'Acceptance-only database assertions (blocked in production)';

    public function handle(AcceptanceDbAssertions $assertions): int
    {
        if (app()->environment('production')) {
            $this->error('acceptance:assert refused: APP_ENV=production');

            return self::FAILURE;
        }

        if (! app()->environment('acceptance', 'testing', 'local')) {
            $this->error('acceptance:assert requires APP_ENV=acceptance|testing|local');

            return self::FAILURE;
        }

        if ($this->option('stock')) {
            $result = $assertions->stockReconciliation();
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $entity = (string) $this->option('entity');
        $key = (string) $this->option('key');
        $value = $this->option('value');
        if ($entity === '' || $key === '' || $value === null) {
            $this->error('--entity --key --value required (or use --stock)');

            return self::FAILURE;
        }

        $expect = [];
        foreach ($this->option('expect') as $pair) {
            if (! str_contains((string) $pair, '=')) {
                continue;
            }
            [$f, $v] = explode('=', (string) $pair, 2);
            $expect[$f] = $v;
        }

        $result = $assertions->assertEntity($entity, $key, $value, $expect);
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
