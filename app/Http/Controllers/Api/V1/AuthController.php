<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Models\FailedLoginAttempt;
use App\Models\User;
use App\Services\Api\ApiTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
  public function login(Request $request): JsonResponse
  {
    $credentials = $request->validate([
      'email' => ['required', 'email'],
      'password' => ['required', 'string'],
      'device_name' => ['nullable', 'string', 'max:120'],
    ]);

    $email = strtolower(trim($credentials['email']));
    $user = User::query()->where('email', $email)->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
      FailedLoginAttempt::record($credentials['email'], $request->ip(), $request->userAgent());

      return $this->error('Invalid email or password.', 401);
    }

    if (in_array($user->role, ['super_admin', 'platform_staff'], true)) {
      return $this->error('Platform admin accounts must use the web admin panel.', 403);
    }

    if (! $user->is_active) {
      return $this->error('Your account is deactivated.', 403, null);
    }

    if ($user->business?->isPendingApproval()) {
      return $this->error('Business is pending approval.', 403);
    }

    if ($user->business && ! $user->business->is_active) {
      return $this->error('Business is suspended.', 403);
    }

    $deviceName = $credentials['device_name'] ?? 'mobile';
    $token = $user->createToken($deviceName)->plainTextToken;

    app(ApiTenantContext::class)->setUser($user);

    AuditLog::logLogin($user);

    return $this->success([
      'token' => $token,
      'token_type' => 'Bearer',
      'user' => $this->userPayload($user),
    ], 'Login successful');
  }

  public function logout(Request $request): JsonResponse
  {
    $user = $request->user();
    if ($user) {
      AuditLog::logLogout($user);
      $request->user()->currentAccessToken()?->delete();
      app(ApiTenantContext::class)->setUser($user);
      app(ApiTenantContext::class)->clear();
    }

    return $this->success(null, 'Logged out');
  }

  public function me(Request $request): JsonResponse
  {
    return $this->success($this->userPayload($request->user()));
  }

  public function switchBusiness(Request $request): JsonResponse
  {
    if ($request->user()->role !== 'owner') {
      return $this->forbidden('Only owners can switch business.');
    }

    $data = $request->validate([
      'business_id' => ['required', 'integer', 'min:1'],
    ]);

    $ctx = app(ApiTenantContext::class);
    $ctx->setUser($request->user());
    $ctx->setActiveBusiness((int) $data['business_id']);

    return $this->success($this->userPayload($request->user()->fresh()), 'Business switched');
  }

  public function switchBranch(Request $request): JsonResponse
  {
    if ($request->user()->role !== 'owner') {
      return $this->forbidden('Only owners can switch branch.');
    }

    $data = $request->validate([
      'branch_id' => ['nullable', 'integer', 'min:1'],
    ]);

    $ctx = app(ApiTenantContext::class);
    $ctx->setUser($request->user());
    $ctx->setActiveBranch(isset($data['branch_id']) ? (int) $data['branch_id'] : null);

    return $this->success($this->userPayload($request->user()->fresh()), 'Branch switched');
  }

  /**
   * @return array<string, mixed>
   */
  private function userPayload(User $user): array
  {
    $ctx = app(ApiTenantContext::class);
    $ctx->setUser($user);
    $user->loadMissing(['business.plan', 'branch', 'role_relation']);

    $permissions = $user->role === 'owner'
      ? collect(config('permissions.groups', []))->flatMap(fn ($g) => array_keys($g))->unique()->values()->all()
      : ($user->role_relation?->permissions ?? []);

    $openShift = \App\Models\Shift::openForUser($user->id, $ctx->businessId());

    return [
      'id' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
      'phone' => $user->phone,
      'role' => $user->role,
      'role_label' => $user->displayRoleName(),
      'locale' => $user->locale ?? 'en',
      'permissions' => $permissions,
      'requires_open_shift' => $user->requiresOpenShift(),
      'needs_shift_opened' => $user->needsShiftOpened(),
      'open_shift_id' => $openShift?->id,
      'business' => $this->businessPayload($ctx),
      'branch' => $this->branchPayload($ctx),
      'available_businesses' => $user->role === 'owner'
        ? $ctx->ownerBusinesses()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values()->all()
        : [],
      'available_branches' => $user->role === 'owner'
        ? $ctx->ownerBranches()->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'is_default' => (bool) $b->is_default])->values()->all()
        : [],
    ];
  }

  /**
   * @return array<string, mixed>|null
   */
  private function businessPayload(ApiTenantContext $ctx): ?array
  {
    $business = $ctx->business();
    if (! $business) {
      return null;
    }

    $business->loadMissing('plan');

    return [
      'id' => $business->id,
      'name' => $business->name,
      'phone' => $business->phone,
      'email' => $business->email,
      'operation_mode' => $business->operation_mode ?? 'retail',
      'currency' => 'TZS',
      'plan' => $business->plan?->name,
    ];
  }

  /**
   * @return array<string, mixed>|null
   */
  private function branchPayload(ApiTenantContext $ctx): ?array
  {
    if ($ctx->user()?->role === 'owner' && $ctx->isViewingAllBranches()) {
      return [
        'id' => null,
        'name' => 'All Branches',
        'viewing_all' => true,
      ];
    }

    $branch = $ctx->branch() ?? $ctx->user()?->branch;
    if (! $branch) {
      return null;
    }

    return [
      'id' => $branch->id,
      'name' => $branch->name,
      'viewing_all' => false,
    ];
  }
}
