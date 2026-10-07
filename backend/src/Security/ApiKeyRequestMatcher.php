<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Sends every request carrying an X-API-Key header to the stateless "api_key" firewall: such requests
 * never open a session nor set a cookie. All the other requests go through the session firewall.
 */
final class ApiKeyRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        return $request->headers->has('X-API-Key');
    }
}
