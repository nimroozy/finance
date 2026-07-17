<?php

use App\Jobs\EvaluateCrmFollowUpsJob;
use App\Jobs\EvaluateEscalationsJob;
use App\Jobs\EvaluateSlaBreachesJob;
use App\Jobs\RunPaymentReconciliationJob;
use App\Jobs\UpdatePromiseStatusesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('zoho:scheduler-tick')
    ->everyMinute()
    ->name('zoho-scheduler-tick')
    ->withoutOverlapping(15);

Schedule::job(new UpdatePromiseStatusesJob)
    ->daily()
    ->name('update-promise-statuses')
    ->withoutOverlapping();

Schedule::job(new RunPaymentReconciliationJob)
    ->daily()
    ->name('payment-reconciliation-daily')
    ->withoutOverlapping();

Schedule::command('assignments:expire-temporary')
    ->hourly()
    ->name('expire-temporary-assignments')
    ->withoutOverlapping();

Schedule::command('whatsapp:promise-reminders')
    ->dailyAt('08:00')
    ->name('whatsapp-promise-reminders')
    ->withoutOverlapping();

Schedule::job(new EvaluateSlaBreachesJob)
    ->everyFiveMinutes()
    ->name('evaluate-sla-breaches')
    ->withoutOverlapping();

Schedule::job(new EvaluateEscalationsJob)
    ->everyFiveMinutes()
    ->name('evaluate-escalations')
    ->withoutOverlapping();

Schedule::job(new EvaluateCrmFollowUpsJob)
    ->everyFifteenMinutes()
    ->name('evaluate-crm-follow-ups')
    ->withoutOverlapping();
