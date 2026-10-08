<?php

namespace App\Controller;

use App\Error\ErrorCode;
use App\Error\ErrorResponse;
use App\Mcp\AllowedHostMiddleware;
use App\Mcp\CodexTools;
use App\Repository\ApiKeyRepository;
use App\Service\ApiKeyService;
use App\Service\KnowledgeService;
use App\Service\SearchService;
use App\Service\TimelineService;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The MCP server an AI connects to (ChatGPT, Claude...). The secret key of the book is part of the address:
 * https://host/mcp/cdx_... , so nothing else has to be configured on the AI side. The key names the book,
 * which is why the tools never take a book identifier, and gives read access only.
 */
final class McpController
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly ApiKeyService $keyService,
        private readonly KnowledgeService $knowledge,
        private readonly TimelineService $timeline,
        private readonly SearchService $search,
        #[Autowire('%kernel.project_dir%/var/mcp-sessions')]
        private readonly string $sessionDirectory,
        #[Autowire('%env(MCP_ALLOWED_HOSTS)%')]
        private readonly string $allowedHosts,
    ) {
    }

    #[Route('/mcp/{token}', requirements: ['token' => 'cdx_[0-9a-f]{40}'], methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function __invoke(string $token, Request $request): Response
    {
        $key = $this->keys->findByToken($token);
        if (null === $key) {
            return ErrorResponse::create(ErrorCode::UNAUTHORIZED);
        }
        $this->keyService->markUsed($key);

        $tools = new CodexTools($key->getBook(), $this->knowledge, $this->timeline, $this->search);

        $server = Server::builder()
            ->setServerInfo('Codex', '1.0.0')
            ->setInstructions(
                'Codex is the library of a book being written: its entries (characters, groups, species, places, items, systems, abilities, concepts, ranks, themes), and the '
                .'events of its timeline. Read it to stay consistent with the story. Start with search or index to find '
                .'the id of an entry, then read it with get_knowledge. index also tells how far the story has been written '
                .'(lastChapter): to continue it, pass atChapter = lastChapter; to rewrite a given chapter, pass '
                .'beforeChapter so that you only see what the reader already knows.',
            )
            ->setSession(new FileSessionStore($this->sessionDirectory))
            ->addTool([$tools, 'index'], name: 'index', title: 'List the entries')
            ->addTool([$tools, 'get_knowledge'], name: 'get_knowledge', title: 'Read an entry')
            ->addTool([$tools, 'timeline'], name: 'timeline', title: 'Read the timeline')
            ->addTool([$tools, 'get_event'], name: 'get_event', title: 'Read an event')
            ->addTool([$tools, 'search'], name: 'search', title: 'Search the book')
            ->build();

        $psrFactory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $psrRequest = (new PsrHttpFactory($psrFactory, $psrFactory, $psrFactory, $psrFactory))->createRequest($request);

        $transport = new StreamableHttpTransport(
            $psrRequest,
            $psrFactory,
            $psrFactory,
            null,
            [
                new CorsMiddleware(),
                new AllowedHostMiddleware(array_map('trim', explode(',', $this->allowedHosts))),
            ],
        );

        return (new HttpFoundationFactory())->createResponse($server->run($transport));
    }
}
