<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('helpers.php');

        if (\Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
            app(\App\Services\PlatformSettingsService::class)->applyMailConfig();
        }

        \Illuminate\Support\Facades\View::composer(
            ['layouts.partials._header', 'layouts.partials._sidebar', 'layouts.app'],
            function ($view) {
                $branchService = app(\App\Services\ActiveBranchService::class);

                $data = [
                    'canSwitchBranch' => $branchService->canSwitch(),
                    'ownerBranches' => $branchService->branches(),
                    'activeBranch' => $branchService->activeBranch(),
                    'activeBranchId' => $branchService->activeBranchId(),
                    'activeBranchLabel' => $branchService->activeBranchLabel(),
                    'viewingAllBranches' => $branchService->isViewingAllBranches(),
                    'dueNoteReminders' => collect(),
                    'dueNoteRemindersCount' => 0,
                    'newNoteReminderToasts' => collect(),
                ];

                $user = auth()->user();
                $data['headerBrand'] = platform_settings('platform_name', 'SP-POS');

                if ($user) {
                    if ($user->role === 'super_admin') {
                        $data['headerBrand'] = platform_settings('platform_name', 'SP-POS');
                    } elseif ($user->business_id) {
                        $user->loadMissing('business');
                        if ($user->business?->name) {
                            $data['headerBrand'] = $user->business->name;
                        }
                    }
                }

                if ($user && $user->role !== 'super_admin' && $user->business_id) {
                    $dueReminders = \App\Models\BusinessNote::where('business_id', $user->business_id)
                        ->where('user_id', $user->id)
                        ->due()
                        ->orderBy('remind_at')
                        ->limit(10)
                        ->get();

                    $toastedIds = session('note_reminder_toasted_ids', []);
                    $newToasts = $dueReminders->whereNotIn('id', $toastedIds)->values();

                    if ($newToasts->isNotEmpty()) {
                        session([
                            'note_reminder_toasted_ids' => array_values(array_unique(array_merge(
                                $toastedIds,
                                $newToasts->pluck('id')->all()
                            ))),
                        ]);
                    }

                    $data['dueNoteReminders'] = $dueReminders;
                    $data['dueNoteRemindersCount'] = $dueReminders->count();
                    $data['newNoteReminderToasts'] = $newToasts;
                }

                $view->with($data);
            }
        );

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if ($user->role === 'super_admin' || $user->role === 'owner') {
                return true;
            }
        });

        $permissions = collect(config('permissions.groups', []))
            ->flatMap(fn ($group) => array_keys($group))
            ->unique()
            ->values()
            ->all();

        foreach ($permissions as $permission) {
            \Illuminate\Support\Facades\Gate::define($permission, function ($user) use ($permission) {
                // Load user's role relation
                $user->loadMissing('role_relation');
                
                if ($user->role_relation && is_array($user->role_relation->permissions)) {
                    return in_array($permission, $user->role_relation->permissions);
                }
                
                return false;
            });
        }
    }
}
