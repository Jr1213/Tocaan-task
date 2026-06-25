<?php

namespace App\Http\Controllers\API;

use App\Actions\LoginAction;
use App\Actions\RegisterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\loginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Service\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private LoginAction $loginAction,
        private RegisterAction $registerAction,
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data   = $request->validated();
        [$user, $token] = $this->registerAction->execute($data);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ]);
    }

    public function login(loginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $data = $this->loginAction->execute($credentials);
        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully.',
            'data'    => [
                'user'  => $data['user'],
                'token' => $data['token'],
            ],
        ]);
    }

    public function me(): JsonResponse
    {
        $user = $this->authService->getUserFromToken();
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data'    => [
                'user' => $user,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();
        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully.',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = $this->authService->refreshToken();
        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data'    => [
                'token' => $token,
            ],
        ]);
    }

    protected function respondWithToken(string $token, ?User $user = null): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user ?? auth('api')->user(),
        ]);
    }
}
