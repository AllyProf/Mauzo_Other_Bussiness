<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiAssistantService;
use App\Services\GroqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAi($request);

        $user = $request->user();
        $business = active_business() ?? $user->business;

        $conversations = AiConversation::query()
            ->where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->where('is_temporary', false)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'title', 'last_message_at', 'updated_at']);

        $activeId = (int) $request->query('c', 0);
        $active = null;
        $messages = collect();

        if ($activeId > 0) {
            $active = AiConversation::query()
                ->where('user_id', $user->id)
                ->where('business_id', $business->id)
                ->where('is_temporary', false)
                ->whereKey($activeId)
                ->first();

            if ($active) {
                $messages = $active->messages()->get(['id', 'role', 'content', 'created_at']);
            }
        }

        $rawFirst = Str::of($user->name ?? 'there')->trim()->explode(' ')->first() ?: 'there';
        $firstName = Str::title(Str::lower($rawFirst));

        return view('ai.index', [
            'conversations' => $conversations,
            'activeConversation' => $active,
            'messages' => $messages,
            'firstName' => $firstName,
            'aiIcon' => mauzo_ai_icon_url(),
            'aiLogo' => asset('gp-assets/img/logo.png'),
        ]);
    }

    public function ask(Request $request, AiAssistantService $assistant): JsonResponse
    {
        $this->authorizeAi($request);

        if (! $assistant->enabled()) {
            return response()->json(['message' => 'AI assistant is not configured yet.'], 503);
        }

        $data = $request->validate([
            'message' => 'required|string|min:2|max:1500',
            'history' => 'nullable|array|max:12',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:8000',
            'conversation_id' => 'nullable|integer|min:1',
            'temporary' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $business = active_business() ?? $user->business;
        if (! $business) {
            return response()->json(['message' => 'No active business selected.'], 422);
        }

        $temporary = (bool) ($data['temporary'] ?? false);
        $conversation = null;
        $history = $this->clipHistory($data['history'] ?? []);

        if (! $temporary) {
            $conversation = $this->resolveConversation(
                $user->id,
                $business->id,
                isset($data['conversation_id']) ? (int) $data['conversation_id'] : null
            );

            if ($conversation->messages()->exists() && $history === []) {
                $history = $this->clipHistory(
                    $conversation->messages()
                        ->orderByDesc('id')
                        ->limit(12)
                        ->get()
                        ->sortBy('id')
                        ->values()
                        ->map(fn (AiMessage $m) => [
                            'role' => $m->role,
                            'content' => $m->content,
                        ])
                        ->all()
                );
            }
        }

        try {
            $result = $assistant->ask(
                $user,
                $business,
                $data['message'],
                $history
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: 'AI request failed. Please try again.',
            ], 502);
        }

        if ($conversation && ! $temporary) {
            if (! $conversation->title) {
                $conversation->title = Str::limit(trim($data['message']), 60, '…');
            }

            $conversation->messages()->create([
                'role' => 'user',
                'content' => $data['message'],
            ]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => Str::limit($result['reply'], 8000, ''),
            ]);
            $conversation->touchLastMessage();
        }

        return response()->json([
            'reply' => $result['reply'],
            'model' => $result['model'],
            'conversation_id' => $conversation?->id,
            'conversation_title' => $conversation?->title,
            'temporary' => $temporary,
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeAi($request);

        $user = $request->user();
        $business = active_business() ?? $user->business;

        if ((int) $conversation->user_id !== (int) $user->id
            || (int) $conversation->business_id !== (int) $business->id) {
            abort(403);
        }

        $conversation->delete();

        return response()->json(['ok' => true]);
    }

    public function transcribe(Request $request, GroqService $groq): JsonResponse
    {
        $this->authorizeAi($request);

        if (! $groq->enabled()) {
            return response()->json(['message' => 'AI is not configured yet.'], 503);
        }

        $request->validate([
            'audio' => 'required|file|max:12288|mimetypes:audio/webm,audio/wav,audio/mpeg,audio/mp4,audio/ogg,video/webm',
        ]);

        $file = $request->file('audio');
        $path = $file->getRealPath();

        try {
            $text = $groq->transcribe(
                $path,
                $file->getClientOriginalName() ?: 'audio.webm',
                app()->getLocale() === 'sw' ? 'sw' : 'en'
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() ?: 'Could not transcribe audio.',
            ], 502);
        }

        if ($text === '') {
            return response()->json(['message' => 'No speech detected. Please try again.'], 422);
        }

        return response()->json(['text' => $text]);
    }

    private function resolveConversation(int $userId, int $businessId, ?int $conversationId): AiConversation
    {
        if ($conversationId) {
            $existing = AiConversation::query()
                ->whereKey($conversationId)
                ->where('user_id', $userId)
                ->where('business_id', $businessId)
                ->where('is_temporary', false)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return AiConversation::create([
            'user_id' => $userId,
            'business_id' => $businessId,
            'is_temporary' => false,
            'last_message_at' => now(),
        ]);
    }

    /**
     * @param  array<int, mixed>  $history
     * @return list<array{role: string, content: string}>
     */
    private function clipHistory(array $history): array
    {
        return collect($history)
            ->filter(fn ($turn) => is_array($turn)
                && in_array(($turn['role'] ?? ''), ['user', 'assistant'], true)
                && filled($turn['content'] ?? null))
            ->map(fn ($turn) => [
                'role' => (string) $turn['role'],
                'content' => mb_substr(trim((string) $turn['content']), 0, 4000),
            ])
            ->take(-12)
            ->values()
            ->all();
    }

    private function authorizeAi(Request $request): void
    {
        $user = $request->user();
        if (! $user || $user->isPlatformAdmin() || ! $user->business_id) {
            abort(403, 'AI assistant is only available for business users.');
        }

        if (! $user->can('use_ai_assistant')) {
            abort(403, 'You do not have permission to use the AI assistant.');
        }

        if (! plan_feature('ai_assistant')) {
            abort(403, 'MauzoLink AI is not included in your subscription plan.');
        }
    }
}
