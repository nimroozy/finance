<?php

namespace App\Services\Reports;

use App\Models\Task;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsReportService
{
    /**
     * @param  list<int>|null  $branchIds
     * @param  array<string, mixed>  $filters
     */
    public function ticketsCsv(?array $branchIds = null, array $filters = []): StreamedResponse
    {
        $query = Ticket::query()->orderBy('id');
        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $this->streamCsv('tickets-export.csv', [
            'id', 'number', 'branch_id', 'type', 'status', 'priority', 'subject',
            'customer_id', 'assignee_id', 'breached_at', 'created_at', 'resolved_at',
        ], $query->cursor(), function (Ticket $t) {
            return [
                $t->id, $t->number, $t->branch_id, $t->type, $t->status, $t->priority, $t->subject,
                $t->customer_id, $t->assignee_id,
                optional($t->breached_at)->toDateTimeString(),
                optional($t->created_at)->toDateTimeString(),
                optional($t->resolved_at)->toDateTimeString(),
            ];
        });
    }

    /**
     * @param  list<int>|null  $branchIds
     * @param  array<string, mixed>  $filters
     */
    public function tasksCsv(?array $branchIds = null, array $filters = []): StreamedResponse
    {
        $query = Task::query()->orderBy('id');
        if ($branchIds !== null) {
            $query->whereIn('branch_id', $branchIds);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->streamCsv('tasks-export.csv', [
            'id', 'number', 'branch_id', 'ticket_id', 'status', 'work_type', 'title',
            'assignee_id', 'started_at', 'completed_at', 'created_at',
        ], $query->cursor(), function (Task $t) {
            return [
                $t->id, $t->number, $t->branch_id, $t->ticket_id, $t->status, $t->work_type, $t->title,
                $t->assignee_id,
                optional($t->started_at)->toDateTimeString(),
                optional($t->completed_at)->toDateTimeString(),
                optional($t->created_at)->toDateTimeString(),
            ];
        });
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<mixed>  $rows
     * @param  callable(mixed): list<mixed>  $mapper
     */
    public function streamCsv(string $filename, array $headers, iterable $rows, callable $mapper): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $mapper) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $mapper($row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<list<mixed>>
     */
    public function toCsvMatrix(Collection $rows, array $columns): array
    {
        $matrix = [$columns];
        foreach ($rows as $row) {
            $matrix[] = array_map(fn ($col) => $row[$col] ?? null, $columns);
        }

        return $matrix;
    }
}
