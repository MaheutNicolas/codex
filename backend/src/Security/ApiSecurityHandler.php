<?php

namespace App\Security;

use App\Api\ApiHelper;
use App\Entity\User;
use App\Error\ErrorCode;
use App\Error\ErrorResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** Answers the Security component's events (login, missing credentials, denied access) in the API's JSON format. */
final class ApiSecurityHandler implements AuthenticationEntryPointInterface, AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    /** A request without credentials reached a protected route. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ErrorResponse::create(ErrorCode::UNAUTHORIZED);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();

        return ApiHelper::json(['id' => $user->getId(), 'username' => $user->getUsername()]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $minutes = (int) ($exception->getMessageData()['%minutes%'] ?? 1);

            return ErrorResponse::create(ErrorCode::TOO_MANY_ATTEMPTS, null, [], ['Retry-After' => (string) ($minutes * 60)]);
        }

        // The same answer for an unknown username and a wrong password, so accounts cannot be guessed.
        return ErrorResponse::create(ErrorCode::LOGIN_FAILED);
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        return ErrorResponse::create(ErrorCode::FORBIDDEN);
    }
}
