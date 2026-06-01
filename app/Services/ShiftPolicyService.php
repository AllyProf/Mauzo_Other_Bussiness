<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Shift;
use Carbon\Carbon;

class ShiftPolicyService
{
    public function settings(Business $business): array
    {
        $automation = $business->automationSettings();

        return [
            'shift_open_mode' => $automation['shift_open_mode'] ?? 'anytime',
            'shift_open_time_from' => $automation['shift_open_time_from'] ?? '06:00',
            'shift_open_time_to' => $automation['shift_open_time_to'] ?? '22:00',
            'shift_open_days' => $automation['shift_open_days'] ?? [0, 1, 2, 3, 4, 5, 6],
            'shift_max_open_duration' => (int) ($automation['shift_max_open_duration'] ?? 1),
            'shift_max_open_unit' => $automation['shift_max_open_unit'] ?? 'days',
            'shift_enforce_max_duration' => (bool) ($automation['shift_enforce_max_duration'] ?? true),
        ];
    }

    public function canOpenShift(Business $business, ?Carbon $at = null): array
    {
        $settings = $this->settings($business);
        $at ??= now();

        if ($settings['shift_open_mode'] === 'anytime') {
            return ['allowed' => true, 'message' => ''];
        }

        $days = array_map('intval', $settings['shift_open_days'] ?? []);
        if ($days !== [] && ! in_array((int) $at->dayOfWeek, $days, true)) {
            return [
                'allowed' => false,
                'message' => 'Shifts can only be opened on allowed days. Check with your manager or try again on an permitted day.',
            ];
        }

        $from = $this->parseTime($settings['shift_open_time_from'] ?? '06:00', $at);
        $to = $this->parseTime($settings['shift_open_time_to'] ?? '22:00', $at);

        if ($from->lte($to)) {
            if ($at->lt($from) || $at->gt($to)) {
                return [
                    'allowed' => false,
                    'message' => 'Shifts can only be opened between '.$from->format('h:i A').' and '.$to->format('h:i A').'.',
                ];
            }
        } else {
            // Overnight window (e.g. 22:00 – 06:00)
            if ($at->lt($from) && $at->gt($to)) {
                return [
                    'allowed' => false,
                    'message' => 'Shifts can only be opened between '.$from->format('h:i A').' and '.$to->format('h:i A').'.',
                ];
            }
        }

        return ['allowed' => true, 'message' => ''];
    }

    public function maxOpenUntil(Shift $shift, Business $business): Carbon
    {
        $settings = $this->settings($business);
        $duration = max(1, (int) $settings['shift_max_open_duration']);
        $openedAt = Carbon::parse($shift->opened_at);

        return $settings['shift_max_open_unit'] === 'weeks'
            ? $openedAt->copy()->addWeeks($duration)
            : $openedAt->copy()->addDays($duration);
    }

    public function shiftOverdueStatus(Shift $shift, Business $business, ?Carbon $at = null): array
    {
        $at ??= now();
        $settings = $this->settings($business);
        $deadline = $this->maxOpenUntil($shift, $business);
        $overdue = $at->gt($deadline);

        $unitLabel = $settings['shift_max_open_unit'] === 'weeks'
            ? ($settings['shift_max_open_duration'] === 1 ? 'week' : 'weeks')
            : ($settings['shift_max_open_duration'] === 1 ? 'day' : 'days');

        $durationText = $settings['shift_max_open_duration'].' '.$unitLabel;

        return [
            'overdue' => $overdue,
            'enforced' => $settings['shift_enforce_max_duration'],
            'deadline' => $deadline,
            'duration_text' => $durationText,
            'message' => $overdue
                ? 'This shift has been open longer than the allowed '.$durationText.'. Close it and submit handover before continuing.'
                : '',
        ];
    }

    public function mustBlockShiftActivity(Shift $shift, Business $business): bool
    {
        $status = $this->shiftOverdueStatus($shift, $business);

        return $status['overdue'] && $status['enforced'];
    }

    public function openWindowLabel(Business $business): string
    {
        $settings = $this->settings($business);

        if ($settings['shift_open_mode'] === 'anytime') {
            return 'Any time';
        }

        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $days = array_map('intval', $settings['shift_open_days'] ?? []);
        $dayLabel = count($days) === 7
            ? 'Every day'
            : implode(', ', array_map(fn ($d) => $dayNames[$d] ?? $d, $days));

        $from = Carbon::createFromFormat('H:i', $settings['shift_open_time_from'] ?? '06:00');
        $to = Carbon::createFromFormat('H:i', $settings['shift_open_time_to'] ?? '22:00');

        return $dayLabel.' · '.$from->format('h:i A').' – '.$to->format('h:i A');
    }

    private function parseTime(string $time, Carbon $reference): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $reference->copy()->setTime((int) $hour, (int) $minute, 0);
    }
}
