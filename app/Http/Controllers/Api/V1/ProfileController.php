<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\UserProfileApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends ApiController
{
    public function __construct(private UserProfileApiService $profiles)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->success($this->profiles->show($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        try {
            $data = $this->profiles->update(
                $request->user(),
                $request->all(),
                $request->file('profile_image')
            );

            return $this->success($data, 'Profile updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }

    public function updatePassword(Request $request): JsonResponse
    {
        try {
            $data = $this->profiles->updatePassword($request->user(), $request->all());

            return $this->success($data, 'Password updated successfully.');
        } catch (ValidationException $e) {
            return $this->error('Validation failed.', 422, $e->errors());
        }
    }
}
