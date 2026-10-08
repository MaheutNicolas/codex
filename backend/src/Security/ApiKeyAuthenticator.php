<?php

namespace App\Security;

use App\Entity\ApiKey;
use App\Error\ErrorCode;
use App\Error\ErrorResponse;
use App\Repository\ApiKeyRepository;
use App\Service\ApiKeyService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/** Authenticates a request by its X-API-Key header. The authenticated user is the owner of the key. */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly ApiKeyService $keyService,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-API-Key');
    }

    public function authenticate(Request $request): Passport
    {
        $token = trim((string) $request->headers->get('X-API-Key'));
        $key = '' === $token ? null : $this->keys->findByToken($token);
        if (null === $key) {
            throw new BadCredentialsException('Unknown API key.');
        }

        $this->keyService->markUsed($key);

        $user = $key->getUser();
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn () => $user));
        $passport->setAttribute('api_key', $key);

        return $passport;
    }

    /** The key travels with the token, so that the rest of the application can tell which book and scope it grants. */
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute('api_key', $passport->getAttribute('api_key'));

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return ErrorResponse::create(ErrorCode::UNAUTHORIZED);
    }
}
