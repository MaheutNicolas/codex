<?php

namespace App\Mcp;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Only answers to the host names of this server (the Host header). The SDK's own protection also rejects
 * every request that carries an Origin header from another site, which is right for a server running on
 * somebody's computer (DNS rebinding) but wrong here: this server is public and each request is authenticated
 * by the secret key in the address, so an AI client that sends an Origin must not be refused.
 */
final class AllowedHostMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $allowedHosts;

    /** @param list<string> $allowedHosts host names without port, e.g. "codexbase.fr" or "[::1]" */
    public function __construct(array $allowedHosts)
    {
        $this->allowedHosts = array_values(array_map('strtolower', $allowedHosts));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $request->getHeaderLine('Host');
        if ('' !== $host && !\in_array($this->hostName($host), $this->allowedHosts, true)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden: Invalid Host header.');
        }

        return $handler->handle($request);
    }

    /** "Example.com:8000" -> "example.com", "[::1]:8000" -> "[::1]" */
    private function hostName(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            return false === $end ? $host : substr($host, 0, $end + 1);
        }

        return explode(':', $host)[0];
    }
}
