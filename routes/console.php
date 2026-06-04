<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:issue-monthly-invoices')
    ->monthlyOn(1, '06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing-invoices.log'));

Schedule::command('communications:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/scheduled-communications.log'));

Schedule::command('notes:send-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/note-reminders.log'));

Schedule::command('debts:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/debt-reminders.log'));

Schedule::command('platform:send-payment-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/payment-reminders.log'));

Schedule::command('platform:auto-suspend-overdue')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/auto-suspend.log'));

Schedule::command('platform:purge-audit-logs')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/audit-purge.log'));
