<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ZohoFailedJobsCleanupCommand extends Command
{
    protected $signature = 'zoho:failed-jobs-cleanup {--apply} {--only-timestamp=1}';

    protected $description = 'Report or remove duplicate permanent Zoho failed queue jobs';

    public function handle(): int
    {
        $onlyTimestamp = filter_var($this->option('only-timestamp'), FILTER_VALIDATE_BOOL);
        $rows = DB::table('failed_jobs')->orderBy('id')->get()->filter(function ($row) use ($onlyTimestamp) {
            $text = strtolower((string) $row->exception.' '.(string) $row->payload);
            $zoho = str_contains($text, 'zoho');
            $timestamp = str_contains($text, 'last_modified_time')
                || str_contains($text, 'timestamp')
                || str_contains($text, 'date format');
            $permanent = $timestamp || str_contains($text, 'permanent_validation');

            return $zoho && ($onlyTimestamp ? $timestamp : $permanent);
        });

        $deleteIds = $rows->groupBy(function ($row) {
            $text = strtolower((string) $row->exception);

            return preg_replace('/\d+|[a-f0-9-]{20,}/', '#', mb_substr($text, 0, 500));
        })->flatMap(fn ($group) => $group->slice(1)->pluck('id'))->values();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => ! $this->option('apply'),
            'only_timestamp' => $onlyTimestamp,
            'matched' => $rows->count(),
            'representatives_preserved' => $rows->count() - $deleteIds->count(),
            'delete_ids' => $deleteIds->all(),
        ];
        $path = 'zoho-reports/failed-jobs-cleanup-'.now()->format('Ymd-His').'.json';
        Storage::disk('zoho_reports')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($this->option('apply') && $deleteIds->isNotEmpty()) {
            DB::table('failed_jobs')->whereIn('id', $deleteIds)->delete();
        }

        $this->line(json_encode($report));

        return self::SUCCESS;
    }
}
