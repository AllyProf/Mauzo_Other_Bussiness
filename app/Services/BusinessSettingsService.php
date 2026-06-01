<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DayClosing;
use App\Models\Item;
use App\Models\OwnerDailyReport;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftStockCheck;
use Carbon\Carbon;

class BusinessSettingsService
{
    public function dashboardAlerts(Business $business): array
    {
        $settings = $business->automationSettings();
        $alerts = [];
        $today = now()->toDateString();

        if ($settings['notify_debt_overdue'] || $settings['notify_debt_due_soon']) {
            $debts = Sale::where('business_id', $business->id)
                ->whereNotIn('payment_status', ['paid', 'cancelled'])
                ->whereColumn('total_amount', '>', 'amount_paid')
                ->get();

            $overdue = $debts->filter(function ($sale) use ($today) {
                if (! $sale->due_date) {
                    return false;
                }

                return Carbon::parse($sale->due_date)->toDateString() < $today;
            });
            $dueSoon = $debts->filter(function ($sale) use ($today, $settings) {
                if (! $sale->due_date) {
                    return false;
                }
                $dueDate = Carbon::parse($sale->due_date)->toDateString();
                $reminderDays = (int) $settings['debt_due_reminder_days'];

                return $dueDate >= $today
                    && $dueDate <= Carbon::parse($today)->addDays($reminderDays)->toDateString();
            });

            if ($settings['notify_debt_overdue'] && $overdue->isNotEmpty()) {
                $total = $overdue->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid);
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'fa-exclamation-circle',
                    'title' => 'Overdue Customer Debts',
                    'message' => $overdue->count().' account(s) overdue · Total owing '.number_format($total, 0).' TZS',
                    'action_url' => route('debts.index', ['filter' => 'overdue']),
                    'action_label' => 'Review Debts',
                ];
            }

            if ($settings['notify_debt_due_soon'] && $dueSoon->isNotEmpty()) {
                $total = $dueSoon->sum(fn ($sale) => (float) $sale->total_amount - (float) $sale->amount_paid);
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-clock-o',
                    'title' => 'Debts Due Soon',
                    'message' => $dueSoon->count().' account(s) due within '.$settings['debt_due_reminder_days'].' day(s) · '.number_format($total, 0).' TZS outstanding',
                    'action_url' => route('debts.index'),
                    'action_label' => 'View Debts',
                ];
            }
        }

        if ($settings['notify_low_stock']) {
            $threshold = (int) $settings['low_stock_threshold'];
            $lowStockCount = Item::where('business_id', $business->id)
                ->where('current_stock', '<=', $threshold)
                ->count();

            if ($lowStockCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-cubes',
                    'title' => 'Low Stock Items',
                    'message' => $lowStockCount.' item(s) at or below '.$threshold.' units in stock',
                    'action_url' => route('items.stock'),
                    'action_label' => 'View Stock',
                ];
            }
        }

        if ($settings['notify_pending_handover']) {
            $pending = DayClosing::where('business_id', $business->id)
                ->where('status', 'submitted')
                ->count();

            if ($pending > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-balance-scale',
                    'title' => 'Pending Handover Verifications',
                    'message' => $pending.' staff reconciliation(s) waiting for your verification',
                    'action_url' => route('day-closing.index'),
                    'action_label' => 'Review Now',
                ];
            }
        }

        if ($settings['notify_finalize_daily_report']) {
            $unfinalized = OwnerDailyReport::where('business_id', $business->id)
                ->where('status', 'draft')
                ->whereHas('dayClosing', fn ($q) => $q->where('status', 'verified'))
                ->count();

            if ($unfinalized > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'icon' => 'fa-list-alt',
                    'title' => 'Unfinalized Master Sheet Days',
                    'message' => $unfinalized.' verified day(s) not yet finalized on the Master Sheet',
                    'action_url' => route('owner-reports.index'),
                    'action_label' => 'Open Master Sheet',
                ];
            }
        }

        if ($settings['notify_unclosed_shifts']) {
            $policy = app(ShiftPolicyService::class);
            $policySettings = $policy->settings($business);
            $duration = max(1, (int) $policySettings['shift_max_open_duration']);
            $unit = $policySettings['shift_max_open_unit'] === 'weeks' ? 'weeks' : 'days';

            $overdueShifts = Shift::where('business_id', $business->id)
                ->where('status', 'open')
                ->get()
                ->filter(fn (Shift $shift) => $policy->shiftOverdueStatus($shift, $business)['overdue']);

            if ($overdueShifts->isNotEmpty()) {
                $unitLabel = $unit === 'weeks'
                    ? ($duration === 1 ? 'week' : 'weeks')
                    : ($duration === 1 ? 'day' : 'days');
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'fa-clock-o',
                    'title' => 'Shifts Left Open Too Long',
                    'message' => $overdueShifts->count().' shift(s) open longer than '.$duration.' '.$unitLabel,
                    'action_url' => route('shifts.index'),
                    'action_label' => 'View Shifts',
                ];
            }
        }

        if ($settings['notify_opening_stock_shortages'] ?? true) {
            $shortCount = ShiftStockCheck::whereHas('shift', fn ($q) => $q->where('business_id', $business->id)->where('status', 'open'))
                ->where('check_type', 'opening')
                ->shortages()
                ->pendingVerification()
                ->count();

            if ($shortCount > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'fa-warning',
                    'title' => 'Opening Stock Shortages',
                    'message' => $shortCount.' item(s) recorded short on currently open shift(s)',
                    'action_url' => route('stock-shortages.index', ['check_type' => 'opening']),
                    'action_label' => 'Review Shortages',
                ];
            }
        }

        return $alerts;
    }
}
