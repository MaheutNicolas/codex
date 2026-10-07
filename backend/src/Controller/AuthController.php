<?php

namespace App\Controller;

use App\Api\ApiHelper;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth')]
final class AuthController
{
    /** Never reached: the json_login authenticator of the firewall answers first (see config/packages/security.yaml). */
    #[Route('/login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('The login is handled by the json_login authenticator.');
    }

    /** Never reached: the logout of the firewall answers first (see LogoutListener). */
    #[Route('/logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('The logout is handled by the firewall.');
    }

    /** Who the current credentials belong to. Works with a session and with an API key. */
    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return ApiHelper::json(['id' => $user->getId(), 'username' => $user->getUsername()]);
    }
}
