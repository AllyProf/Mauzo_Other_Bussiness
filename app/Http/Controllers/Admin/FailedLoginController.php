<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Concerns\EnsuresPlatformAdmin;
use App\Models\FailedLoginAttempt;
use Illuminate\Http\Request;

class FailedLoginController extends Controller
{
    use EnsuresPlatformAdmin;

    public function index(Request $request)
    {
        $this->ensurePlatformAdmin('security');

        $attempts = FailedLoginAttempt::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim($request->search);
                $q->where(function ($inner) use ($search) {
                    $inner->where('login_identifier', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('attempted_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('attempted_at', '<=', $request->date_to))
            ->orderByDesc('attempted_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.security.failed-logins', compact('attempts'));
    }
}
