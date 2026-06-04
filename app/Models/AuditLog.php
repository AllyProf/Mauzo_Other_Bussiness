<?php

namespace App\Models;

use App\Services\IpGeolocationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'business_id', 'action', 'description', 'ip_address', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public static function log(string $action, string $description, ?int $businessId = null, ?int $userId = null): self
    {
        $authUser = Auth::user();

        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'business_id' => $businessId ?? $authUser?->business_id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logLogin(User $user): void
    {
        self::create([
            'user_id' => $user->id,
            'business_id' => $user->business_id,
            'action' => 'LOGIN',
            'description' => sprintf(
                '%s (%s) signed in',
                $user->name,
                $user->displayRoleName()
            ),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logLogout(?User $user): void
    {
        if (! $user) {
            return;
        }

        self::create([
            'user_id' => $user->id,
            'business_id' => $user->business_id,
            'action' => 'LOGOUT',
            'description' => sprintf(
                '%s (%s) signed out',
                $user->name,
                $user->displayRoleName()
            ),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function routeAction(string $routeName): string
    {
        return strtoupper(str_replace('.', '_', $routeName));
    }

    public static function routeDescription(User $user, Request $request): string
    {
        $routeName = $request->route()?->getName() ?? 'unknown';
        $friendly = str_replace(['.', '_'], [' → ', ' '], $routeName);

        return sprintf(
            '%s (%s) — %s %s',
            $user->name,
            $user->displayRoleName(),
            $request->method(),
            $friendly
        );
    }

    public function actionLabel(): string
    {
        return str_replace('_', ' ', $this->action);
    }

    public function descriptionExcerpt(int $limit = 80): string
    {
        $text = trim((string) ($this->description ?? ''));

        if ($text === '') {
            return '—';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit).'…';
    }

    public function ipLocationLabel(): string
    {
        return app(IpGeolocationService::class)->formatLabel($this->ip_address);
    }

    /**
     * @return array{city: ?string, region: ?string, country: ?string, isp: ?string, label: string, is_local: bool}
     */
    public function ipLocation(): array
    {
        return app(IpGeolocationService::class)->lookup($this->ip_address);
    }

    public function badgeClass(): string
    {
        return match (true) {
            $this->action === 'LOGIN' => 'badge-success',
            $this->action === 'LOGOUT' => 'badge-secondary',
            str_contains($this->action, 'CREATE') || str_contains($this->action, 'STORE') => 'badge-success',
            str_contains($this->action, 'DELETE') || str_contains($this->action, 'DESTROY') || str_contains($this->action, 'SUSPEND') => 'badge-danger',
            str_contains($this->action, 'IMPERSONATE') => 'badge-warning',
            str_contains($this->action, 'UPDATE') || str_contains($this->action, 'EDIT') => 'badge-info',
            default => 'badge-light border',
        };
    }

    public function scopeFiltered($query, array $filters = [], ?int $restrictBusinessId = null)
    {
        if ($restrictBusinessId) {
            $query->where('business_id', $restrictBusinessId);
        } elseif (! empty($filters['business_id'])) {
            $query->where('business_id', $filters['business_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($inner) use ($search) {
                $inner->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (($filters['type'] ?? '') === 'login') {
            $query->whereIn('action', ['LOGIN', 'LOGOUT']);
        } elseif (($filters['type'] ?? '') === 'actions') {
            $query->whereNotIn('action', ['LOGIN', 'LOGOUT']);
        }

        return $query;
    }
}
