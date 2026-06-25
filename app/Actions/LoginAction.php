<?php

namespace App\Actions;

use App\Service\AuthService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LoginAction
{
    public function __construct(private AuthService $authService) {}
    public function execute(array $data): array
    {
        $user = $this->authService->findUserByEmail($data['email']);
        if (!$user || !$this->authService->verifyPassword($user, $data['password'])) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Password or email is incorrect');
        }
        $token = $this->authService->generateToken($user);
        return ['token' => $token, 'user' => $user];
    }
}
