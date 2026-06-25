<?php

namespace App\Actions;

use App\Models\User;
use App\Service\AuthService;

class RegisterAction
{
    public function __construct(private AuthService $authService) {}

    /**
     * Register a new user and issue a JWT for them.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: User, 1: string}
     */
    public function execute(array $data): array
    {
        $user = $this->authService->createUser($data);
        $token = $this->authService->generateToken($user);

        return [$user, $token];
    }
}
