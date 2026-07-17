<?php

namespace App\Console\Commands;

use App\Events\BusinessNotificationRequested;
use App\Models\PromiseToPay;
use Illuminate\Console\Command;

class SendPromiseDueWhatsAppRemindersCommand extends Command
{
    protected $signature = 'whatsapp:promise-reminders {--dry-run}';

    protected $description = 'Queue WhatsApp notification intents for promises due today';

    public function handle(): int
    {
        $promises = PromiseToPay::withoutGlobalScopes()
            ->with(['customer', 'collector.user'])
            ->whereIn('status', PromiseToPay::OPEN_STATUSES)
            ->whereDate('promised_date', today())
            ->get();

        if (! $this->option('dry-run')) {
            foreach ($promises as $promise) {
                BusinessNotificationRequested::dispatch('promise_due_reminder', [
                    'branch_id' => $promise->branch_id,
                    'customer_id' => $promise->customer_id,
                    'user_id' => $promise->collector?->user_id,
                    'phone' => $promise->customer?->mobile ?: $promise->customer?->phone,
                    'title' => 'Promise due today',
                    'promise_id' => $promise->id,
                    'amount' => $promise->amount,
                ]);
            }
        }

        $this->info("Promise reminders: {$promises->count()}");

        return self::SUCCESS;
    }
}
