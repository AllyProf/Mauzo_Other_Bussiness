<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessOnboarding;
use App\Models\Category;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;

class OnboardingService
{
    public const STEPS = [
        'profile_complete' => 'Complete business profile',
        'first_category' => 'Add a product category',
        'first_item' => 'Add your first item',
        'first_sale' => 'Record a sale',
        'invite_staff' => 'Invite staff member',
    ];

    public function forBusiness(Business $business): BusinessOnboarding
    {
        return BusinessOnboarding::firstOrCreate(
            ['business_id' => $business->id],
            ['completed_steps' => []]
        );
    }

    /**
     * @return array<string, array{label: string, done: bool, done_at: string|null}>
     */
    public function checklist(Business $business): array
    {
        $record = $this->forBusiness($business);
        $completed = $record->completed_steps ?? [];
        $detected = $this->detectCompletedSteps($business);
        $merged = array_unique(array_merge(array_keys($completed), array_keys($detected)));

        $checklist = [];

        foreach (self::STEPS as $key => $label) {
            $done = isset($completed[$key]) || isset($detected[$key]);
            $doneAt = $completed[$key] ?? ($detected[$key] ?? null);

            $checklist[$key] = [
                'label' => $label,
                'done' => $done,
                'done_at' => $doneAt,
            ];
        }

        $allDone = collect($checklist)->every(fn ($step) => $step['done']);

        if ($allDone && ! $record->completed_at) {
            $record->update(['completed_at' => now()]);
        }

        return $checklist;
    }

    public function progressPercent(Business $business): int
    {
        $checklist = $this->checklist($business);
        $total = count($checklist);
        $done = collect($checklist)->where('done', true)->count();

        return $total > 0 ? (int) round(($done / $total) * 100) : 0;
    }

    public function syncDetectedSteps(Business $business): BusinessOnboarding
    {
        $record = $this->forBusiness($business);
        $completed = $record->completed_steps ?? [];

        foreach ($this->detectCompletedSteps($business) as $key => $timestamp) {
            if (! isset($completed[$key])) {
                $completed[$key] = $timestamp;
            }
        }

        $record->update(['completed_steps' => $completed]);

        return $record->fresh();
    }

    /**
     * @return array<string, string>
     */
    private function detectCompletedSteps(Business $business): array
    {
        $now = now()->toDateTimeString();
        $steps = [];

        if (filled($business->address) && filled($business->phone) && filled($business->contact_person)) {
            $steps['profile_complete'] = $now;
        }

        if (Category::where('business_id', $business->id)->exists()) {
            $steps['first_category'] = $now;
        }

        if (Item::where('business_id', $business->id)->exists()) {
            $steps['first_item'] = $now;
        }

        if (Sale::where('business_id', $business->id)->where('payment_status', '!=', 'cancelled')->exists()) {
            $steps['first_sale'] = $now;
        }

        if (User::where('business_id', $business->id)->where('role', 'staff')->exists()) {
            $steps['invite_staff'] = $now;
        }

        return $steps;
    }
}
