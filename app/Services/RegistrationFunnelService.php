<?php

namespace App\Services;

use App\Models\RegistrationFunnelEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationFunnelService
{
    public function track(Request $request, string $event, array $metadata = []): void
    {
        RegistrationFunnelEvent::create([
            'session_id' => $this->resolveSessionId($request),
            'event' => $event,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function resolveSessionId(Request $request): string
    {
        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        $deviceId = trim((string) $request->header('X-Device-Id', ''));
        if ($deviceId !== '') {
            return substr('api:'.$deviceId, 0, 64);
        }

        return 'api:'.substr(hash('sha256', (string) $request->ip().'|'.(string) $request->userAgent()), 0, 59);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(?int $days = 30): array
    {
        $since = now()->subDays($days);

        $counts = RegistrationFunnelEvent::query()
            ->select('event', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $since)
            ->groupBy('event')
            ->pluck('total', 'event');

        $landing = (int) ($counts['landing_view'] ?? 0);
        $registerStart = (int) ($counts['register_form_view'] ?? 0);
        $codeSent = (int) ($counts['verification_code_sent'] ?? 0);
        $submitted = (int) ($counts['registration_submitted'] ?? 0);
        $approved = (int) ($counts['registration_approved'] ?? 0);

        return [
            'days' => $days,
            'steps' => [
                ['key' => 'landing_view', 'label' => 'Landing Views', 'count' => $landing],
                ['key' => 'register_form_view', 'label' => 'Registration Started', 'count' => $registerStart],
                ['key' => 'verification_code_sent', 'label' => 'SMS Code Sent', 'count' => $codeSent],
                ['key' => 'registration_submitted', 'label' => 'Registrations Submitted', 'count' => $submitted],
                ['key' => 'registration_approved', 'label' => 'Approved', 'count' => $approved],
            ],
            'conversion_rate' => $landing > 0 ? round(($submitted / $landing) * 100, 1) : 0,
            'approval_rate' => $submitted > 0 ? round(($approved / $submitted) * 100, 1) : 0,
        ];
    }
}
