<?php

namespace App\Mcp;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Error\ApiException;
use App\Service\KnowledgeService;
use App\Service\SearchService;
use App\Service\TimelineService;
use Mcp\Exception\ToolCallException;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The tools an AI can call on one book (the one its key was created for). They only read: each one asks
 * the same services as the REST routes. The descriptions are written for the AI that reads them.
 * A failure is reported to the AI as a tool error with a readable message, so that it can correct its call.
 */
#[Exclude]
final class CodexTools
{
    private const MAX_EVENTS = 200;

    public function __construct(
        private readonly Book $book,
        private readonly KnowledgeService $knowledge,
        private readonly TimelineService $timeline,
        private readonly SearchService $search,
    ) {
    }

    /**
     * Lists every entry of the book (characters, places, systems) with its id, name, type and aliases.
     * Call it once at the start: it lets you find the id of an entry from a name or a nickname.
     * Then read an entry with get_knowledge.
     *
     * @param string|null $type Only entries of this type: character, place or system.
     *
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function index(?string $type = null): array
    {
        if (null !== $type && !\in_array($type, Knowledge::TYPES, true)) {
            throw new ToolCallException(\sprintf('The type must be one of: %s.', implode(', ', Knowledge::TYPES)));
        }
        $entries = $this->knowledge->lexicon($this->book, $type);

        return ['data' => $entries, 'total' => \count($entries)];
    }

    /**
     * Reads one entry in full (summary and description) together with the events it takes part in,
     * in the order of the story world, each with the role of the entry. Use an id from index or search.
     *
     * @param string   $id             The id of the entry, e.g. "aldric".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later events are hidden.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one (use it when writing this chapter).
     * @param bool     $includeSecrets Also show events the reader does not know yet and events never told. Use it only for the author's own knowledge.
     *
     * @return array<string, mixed>
     */
    public function get_knowledge(string $id, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false): array
    {
        return $this->call(fn () => $this->knowledge->sheet(
            $this->book,
            $id,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            new Page(self::MAX_EVENTS),
        ));
    }

    /**
     * Reads the timeline: the events of the story in the order they happen in the world (not the order they
     * are told). Each event has its title, summary, world date, chapter where the reader discovers it
     * (null: never told) and its participants. Use get_event for the full detail of one event.
     *
     * @param int|null    $atChapter      The reader has read chapters 1 to this one (inclusive): later events are hidden.
     * @param int|null    $beforeChapter  The reader has read chapters 1 to this one minus one (use it when writing this chapter).
     * @param bool        $includeSecrets Also show events the reader does not know yet and events never told. Use it only for the author's own knowledge.
     * @param string|null $knowledgeId    Only the events this entry takes part in.
     * @param int         $limit          How many events to return (1 to 200).
     * @param int         $offset         How many events to skip, to read the next page.
     *
     * @return array<string, mixed>
     */
    public function timeline(
        ?int $atChapter = null,
        ?int $beforeChapter = null,
        bool $includeSecrets = false,
        ?string $knowledgeId = null,
        int $limit = 50,
        int $offset = 0,
    ): array {
        if ($limit < 1 || $limit > self::MAX_EVENTS || $offset < 0) {
            throw new ToolCallException(\sprintf('The limit must be between 1 and %d and the offset 0 or more.', self::MAX_EVENTS));
        }

        return $this->call(fn () => $this->timeline->timeline(
            $this->book,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            $knowledgeId,
            new Page($limit, $offset),
        ));
    }

    /**
     * Reads one event in full: summary, detail, world date, chapter and participants with their roles.
     *
     * @param string   $id             The id of the event, e.g. "evt-0042" (from timeline or search).
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive).
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one.
     * @param bool     $includeSecrets Allow reading an event the reader does not know yet or that is never told.
     *
     * @return array<string, mixed>
     */
    public function get_event(string $id, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false): array
    {
        return $this->call(fn () => $this->timeline->event(
            $this->book,
            $id,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
        ));
    }

    /**
     * Searches the entries and the events by words. Finds names, nicknames (aliases), tags and words in the
     * texts, best matches first. Each result has a kind ("knowledge" or "event"), an id and a summary: read
     * it in full with get_knowledge or get_event.
     *
     * @param string   $query          The words to look for, e.g. "oath citadel".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later events are hidden.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one.
     * @param bool     $includeSecrets Also search events the reader does not know yet and events never told.
     * @param int      $limit          How many results to return (1 to 50).
     *
     * @return array<string, mixed>
     */
    public function search(string $query, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false, int $limit = 20): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $terms) {
            throw new ToolCallException('The query must contain at least one word.');
        }
        if ($limit < 1 || $limit > 50) {
            throw new ToolCallException('The limit must be between 1 and 50.');
        }

        return $this->search->search(
            $this->book,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            \array_slice($terms, 0, 10),
            $limit,
        );
    }

    private function viewpoint(?int $atChapter, ?int $beforeChapter, bool $includeSecrets): Viewpoint
    {
        if (null !== $atChapter && null !== $beforeChapter) {
            throw new ToolCallException('Use only one of atChapter and beforeChapter.');
        }
        foreach (['atChapter' => $atChapter, 'beforeChapter' => $beforeChapter] as $name => $chapter) {
            if (null !== $chapter && $chapter < 1) {
                throw new ToolCallException(\sprintf('%s must be 1 or more.', $name));
            }
        }

        return new Viewpoint($atChapter ?? (null === $beforeChapter ? null : $beforeChapter - 1), $includeSecrets);
    }

    /**
     * @template T
     *
     * @param callable(): T $read
     *
     * @return T
     */
    private function call(callable $read): mixed
    {
        try {
            return $read();
        } catch (ApiException $e) {
            // "not found" and the like: the message is what the AI needs to correct its call.
            throw new ToolCallException($e->getMessage().($e->details ? ' '.json_encode($e->details, \JSON_UNESCAPED_UNICODE) : ''), 0, $e);
        }
    }
}
