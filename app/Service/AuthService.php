<?php

namespace App\Service;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function verifyPassword(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    public function generateToken(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    public function getUserFromToken(): ?User
    {
        $user = request()->user();

        return $user;
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function refreshToken(): string
    {
        // Issue a fresh token and blacklist the previous one.
        return auth('api')->refresh(true, true);
    }
}
