<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\BusinessNote;
use App\Services\BusinessNoteReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Same as web /notes: personal notes with optional reminder (SMS sent when remind_at is reached).
 */
class BusinessNoteController extends ApiController
{
    public function __construct(private BusinessNoteReminderService $noteReminders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request)) {
            return $deny;
        }

        $filter = $request->query('filter', 'active');
        if (! in_array($filter, ['active', 'completed', 'all'], true)) {
            $filter = 'active';
        }

        $query = $this->ownedNotes($request)->latest();
        if ($filter === 'active') {
            $query->active();
        } elseif ($filter === 'completed') {
            $query->completed();
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(fn ($inner) => $inner->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%"));
        }

        $notes = $query->paginate(max(1, min(50, (int) $request->get('per_page', 15))));

        return $this->success([
            'stats' => [
                'active' => $this->ownedNotes($request)->active()->count(),
                'due' => $this->ownedNotes($request)->due()->count(),
                'upcoming' => $this->ownedNotes($request)->upcoming()->count(),
            ],
            'filter' => $filter,
            'notes' => collect($notes->items())->map(fn (BusinessNote $n) => $this->payload($n))->values(),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'total' => $notes->total(),
            ],
        ]);
    }

    public function show(Request $request, BusinessNote $note): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        return $this->success(['note' => $this->payload($note)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request)) {
            return $deny;
        }

        $note = BusinessNote::create([
            'business_id' => $request->user()->business_id,
            'user_id' => $request->user()->id,
            ...$this->validatedData($request),
            'reminder_sms_sent_at' => null,
        ]);

        $this->trySendDueReminder($note);

        return $this->success(['note' => $this->payload($note->fresh())], 'Note saved.', 201);
    }

    public function update(Request $request, BusinessNote $note): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        $data = $this->validatedData($request);

        $newRemindAt = filled($data['remind_at'] ?? null) ? Carbon::parse($data['remind_at']) : null;
        if (($note->remind_at?->toDateTimeString()) !== ($newRemindAt?->toDateTimeString())) {
            $data['reminder_sms_sent_at'] = null;
        }

        $note->update($data);
        $this->trySendDueReminder($note->fresh());

        return $this->success(['note' => $this->payload($note->fresh())], 'Note updated.');
    }

    public function destroy(Request $request, BusinessNote $note): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        $note->delete();

        return $this->success(null, 'Note deleted.');
    }

    public function complete(Request $request, BusinessNote $note): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        if (! $note->isCompleted()) {
            $note->update(['completed_at' => now()]);
        }

        return $this->success(['note' => $this->payload($note->fresh())], 'Note marked as done.');
    }

    /**
     * Called by the app when a reminder fires on the device, so the SMS does not
     * wait for the scheduler. Safe to call repeatedly: it sends at most once.
     */
    public function sendReminder(Request $request, BusinessNote $note, BusinessNoteReminderService $reminders): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        $sent = $note->reminder_sms_sent_at === null && $reminders->sendReminder($note);

        return $this->success([
            'sent' => $sent,
            'note' => $this->payload($note->fresh()),
        ], $sent ? 'Reminder SMS sent.' : 'Reminder already handled.');
    }

    public function reopen(Request $request, BusinessNote $note): JsonResponse
    {
        if ($deny = $this->ensureNotesUser($request) ?? $this->ensureOwnsNote($request, $note)) {
            return $deny;
        }

        $note->update(['completed_at' => null]);

        return $this->success(['note' => $this->payload($note->fresh())], 'Note reopened.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(BusinessNote $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'display_title' => $note->displayTitle(),
            'body' => $note->body,
            'remind_at' => $note->remind_at?->toIso8601String(),
            'remind_at_label' => $note->remind_at?->format('d M Y, H:i'),
            'is_due' => $note->isDue(),
            'is_completed' => $note->isCompleted(),
            'completed_at' => $note->completed_at?->toIso8601String(),
            'reminder_sms_sent_at' => $note->reminder_sms_sent_at?->toIso8601String(),
            'status' => $note->isCompleted() ? 'completed' : ($note->isDue() ? 'due' : ($note->remind_at ? 'upcoming' : 'active')),
            'status_label' => $note->statusLabel(),
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{title: string|null, body: string, remind_at: string|null}
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string|max:5000',
            'remind_at' => 'nullable|date',
        ]);

        return [
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
            'remind_at' => ! empty($validated['remind_at']) ? Carbon::parse($validated['remind_at'])->toDateTimeString() : null,
        ];
    }

    private function ownedNotes(Request $request)
    {
        return BusinessNote::where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id);
    }

    private function ensureNotesUser(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user->role === 'super_admin' || ! $user->business_id) {
            return $this->forbidden();
        }

        return $this->authorizeApiAny(['manage_notes']);
    }

    private function ensureOwnsNote(Request $request, BusinessNote $note): ?JsonResponse
    {
        if ((int) $note->business_id !== (int) $request->user()->business_id || (int) $note->user_id !== (int) $request->user()->id) {
            return $this->forbidden('You can only manage your own notes.');
        }

        return null;
    }

    private function trySendDueReminder(BusinessNote $note): void
    {
        if ($note->remind_at === null || $note->remind_at->gt(now())) {
            return;
        }

        try {
            $this->noteReminders->sendReminder($note);
        } catch (\Throwable) {
            // Non-blocking
        }
    }
}
