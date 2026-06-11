<?php

namespace App\Http\Controllers;

use App\Models\BusinessNote;
use App\Services\BusinessNoteReminderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessNoteController extends Controller
{
    public function __construct(private BusinessNoteReminderService $noteReminders)
    {
    }

    public function index(Request $request)
    {
        $this->ensureBusinessUser();

        $filter = $request->query('filter', 'active');
        if (! in_array($filter, ['active', 'completed', 'all'], true)) {
            $filter = 'active';
        }

        $query = BusinessNote::where('business_id', Auth::user()->business_id)
            ->where('user_id', Auth::id())
            ->latest();

        if ($filter === 'active') {
            $query->active();
        } elseif ($filter === 'completed') {
            $query->completed();
        }

        $notes = $query->paginate(15)->withQueryString();

        $stats = [
            'active' => BusinessNote::where('business_id', Auth::user()->business_id)
                ->where('user_id', Auth::id())
                ->active()
                ->count(),
            'due' => BusinessNote::where('business_id', Auth::user()->business_id)
                ->where('user_id', Auth::id())
                ->due()
                ->count(),
            'upcoming' => BusinessNote::where('business_id', Auth::user()->business_id)
                ->where('user_id', Auth::id())
                ->upcoming()
                ->count(),
        ];

        $editNote = null;
        if ($request->filled('edit')) {
            $editNote = $this->findOwnedNote((int) $request->query('edit'));
        }

        return view('notes.index', compact('notes', 'stats', 'filter', 'editNote'));
    }

    public function store(Request $request)
    {
        $this->ensureBusinessUser();

        $data = $this->validatedData($request);

        $note = BusinessNote::create([
            'business_id' => Auth::user()->business_id,
            'user_id' => Auth::id(),
            ...$data,
            'reminder_sms_sent_at' => null,
        ]);

        $this->trySendDueReminder($note);

        return redirect()->route('notes.index')->with('success', 'Note saved.');
    }

    public function update(Request $request, BusinessNote $note)
    {
        $this->ensureBusinessUser();
        $this->ensureOwnsNote($note);

        $data = $this->validatedData($request);

        $newRemindAt = filled($data['remind_at'] ?? null) ? Carbon::parse($data['remind_at']) : null;
        $oldRemindAt = $note->remind_at;
        $remindAtChanged = ($oldRemindAt?->toDateTimeString() ?? null) !== ($newRemindAt?->toDateTimeString() ?? null);

        if ($remindAtChanged) {
            $data['reminder_sms_sent_at'] = null;
        }

        $note->update($data);

        $this->trySendDueReminder($note->fresh());

        return redirect()->route('notes.index')->with('success', 'Note updated.');
    }

    public function destroy(BusinessNote $note)
    {
        $this->ensureBusinessUser();
        $this->ensureOwnsNote($note);

        $note->delete();

        return redirect()->route('notes.index')->with('success', 'Note deleted.');
    }

    public function complete(BusinessNote $note)
    {
        $this->ensureBusinessUser();
        $this->ensureOwnsNote($note);

        if (! $note->isCompleted()) {
            $note->update(['completed_at' => now()]);
        }

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->back()->with('success', 'Note marked as done.');
    }

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
            'remind_at' => ! empty($validated['remind_at']) ? $validated['remind_at'] : null,
        ];
    }

    private function findOwnedNote(int $id): BusinessNote
    {
        return BusinessNote::where('business_id', Auth::user()->business_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }

    private function ensureOwnsNote(BusinessNote $note): void
    {
        if ((int) $note->business_id !== (int) Auth::user()->business_id
            || (int) $note->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function ensureBusinessUser(): void
    {
        $user = Auth::user();

        if (! $user || $user->role === 'super_admin' || ! $user->business_id) {
            abort(403);
        }

        $this->authorizeAny(['manage_notes']);
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
