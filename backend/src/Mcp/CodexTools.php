<?php

namespace App\Mcp;

use App\Api\Page;
use App\Api\Viewpoint;
use App\Entity\Book;
use App\Entity\Knowledge;
use App\Error\ApiException;
use App\Service\KnowledgeService;
use App\Service\RelatedService;
use App\Service\RelationshipService;
use App\Service\RelationStateService;
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
    private const RELATIONS_IN_SHEET = 30;

    public function __construct(
        private readonly Book $book,
        private readonly KnowledgeService $knowledge,
        private readonly TimelineService $timeline,
        private readonly SearchService $search,
        private readonly RelatedService $related,
        private readonly RelationStateService $relationStates,
        private readonly RelationshipService $relationship,
    ) {
    }

    /**
     * Lists every entry of the book (characters, places, systems) with its id, name, type and aliases, and tells
     * how far the story has been written. Call it once at the start: it lets you find the id of an entry from a
     * name or a nickname. Then read an entry with get_knowledge. The "book" part gives the title and
     * "lastChapter", the highest chapter in which an event is told (null if none yet): to continue the story
     * after it, pass atChapter = lastChapter to the other tools.
     *
     * @param string|null $type Only entries of this type: character, group, species, place, item, system, ability, concept, rank, theme.
     *
     * @return array<string, mixed>
     */
    public function index(?string $type = null): array
    {
        if (null !== $type && !\in_array($type, Knowledge::TYPES, true)) {
            throw new ToolCallException(\sprintf('The type must be one of: %s.', implode(', ', Knowledge::TYPES)));
        }
        $entries = $this->knowledge->lexicon($this->book, $type);

        return [
            'book' => ['title' => $this->book->getName(), 'lastChapter' => $this->timeline->lastChapter($this->book)],
            'data' => $entries,
            'total' => \count($entries),
        ];
    }

    /**
     * Reads one entry in full (summary and description) together with the events it takes part in, in the order
     * of the story world, each with the role of the entry (not the other participants: read the event for those).
     * "events.total" is how many events there are in all; read the next ones with offset (or list all of them
     * with timeline and knowledgeId, which also shows the other participants). It also gives the relations of the
     * entry as they stand at the chapter reached ("relations": for each other entry, the current type, a sentence
     * naming both, the chapter it holds since and a note; the 30 most recently changed, "relations.total" says how
     * many there are). Use get_relations for the whole history or the next ones. Use an id from index or search.
     *
     * @param string   $id             The id of the entry, e.g. "aldric".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later events are hidden.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one (use it when writing this chapter).
     * @param bool     $includeSecrets Also show events the reader does not know yet and events never told. Use it only for the author's own knowledge.
     * @param int      $limit          How many of its events to return (1 to 200).
     * @param int      $offset         How many of its events to skip, to read the next ones.
     *
     * @return array<string, mixed>
     */
    public function get_knowledge(string $id, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false, int $limit = 30, int $offset = 0): array
    {
        $this->checkPage($limit, $offset);
        $viewpoint = $this->viewpoint($atChapter, $beforeChapter, $includeSecrets);

        $sheet = $this->withoutDates($this->call(fn () => $this->knowledge->sheet($this->book, $id, $viewpoint, new Page($limit, $offset))));
        $sheet['relations'] = $this->call(fn () => $this->relationStates->page($this->book, $id, $viewpoint, false, new Page(self::RELATIONS_IN_SHEET)));

        return $sheet;
    }

    /**
     * Reads the relations of one entry (allies, enemies, mentors, members of a group...) as they stand at the chapter
     * reached. Only characters, groups and species have relations (a place or an item is linked to the story by its
     * events: use timeline with its id). For each other entry it gives the current type, a sentence naming both
     * entries (so that the direction is clear), the chapter it has held since (null: since the start of the book) and
     * a note. The most recently changed come first. Relations change over the story: with withHistory, each one also lists all its states in
     * order (for example allies from the start, enemies from chapter 6, no longer linked from chapter 9), and the
     * pairs that are no longer linked are listed too, marked "ended".
     *
     * @param string   $id             The id of the entry, e.g. "aldric".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later changes are hidden.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one (use it when writing this chapter).
     * @param bool     $includeSecrets Also show relations the reader does not know yet. Use it only for the author's own knowledge.
     * @param bool     $withHistory    Also list every earlier state of each relation, and the ended ones.
     * @param int      $limit          How many relations to return (1 to 200).
     * @param int      $offset         How many relations to skip, to read the next ones.
     *
     * @return array<string, mixed>
     */
    public function get_relations(string $id, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false, bool $withHistory = false, int $limit = 30, int $offset = 0): array
    {
        $this->checkPage($limit, $offset);

        return $this->call(fn () => $this->relationStates->page(
            $this->book,
            $id,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            $withHistory,
            new Page($limit, $offset),
        ));
    }

    /**
     * Looks up how TWO entries stand with each other: use it before writing a scene with two characters (only
     * characters, groups and species have relations; for anything else the answer lists the shared events only). It gives
     * the "status" of the pair ("linked", "ended" when they used to be linked, or "none"), the current "relation"
     * (type, a sentence naming both, the chapter it has held since, a note), the "history" of the pair (every state
     * in order, e.g. allies from the start, enemies from chapter 6, no longer linked from chapter 9), and the events
     * they both take part in ("sharedEvents", with the role of each). The order of the two ids does not change the
     * answer, only the "direction" of a relation that reads one way (e.g. mentor). Use the ids from index or search.
     *
     * @param string   $id             The id of the first entry, e.g. "aldric".
     * @param string   $otherId        The id of the second entry, e.g. "mira".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later changes and events are hidden.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one (use it when writing this chapter).
     * @param bool     $includeSecrets Also show relations and events the reader does not know yet. Use it only for the author's own knowledge.
     * @param int      $eventsLimit    How many shared events to list (1 to 50).
     *
     * @return array<string, mixed>
     */
    public function get_relationship(string $id, string $otherId, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false, int $eventsLimit = 10): array
    {
        if ($eventsLimit < 1 || $eventsLimit > 50) {
            throw new ToolCallException('The limit must be between 1 and 50.');
        }

        return $this->call(fn () => $this->relationship->between(
            $this->book,
            $id,
            $otherId,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            $eventsLimit,
        ));
    }

    /**
     * Finds the entries linked to one entry: first those it has a relation with (allies, enemies, mentors...: the
     * "relation" part gives the current type and a sentence), then those that take part in the same events, the most
     * often first. Use it to see who and what surrounds a character or a place before writing a scene with it. Each
     * result says where it comes from ("source": relation, events or both) and, for shared events, how many and in
     * which chapters (first and last). "total" counts every linked entry. Read an entry in full with get_knowledge,
     * its relations and their history with get_relations, or the events they share with timeline and knowledgeId.
     *
     * @param string   $id             The id of the entry, e.g. "aldric".
     * @param int|null $atChapter      The reader has read chapters 1 to this one (inclusive): later events do not count.
     * @param int|null $beforeChapter  The reader has read chapters 1 to this one minus one.
     * @param bool     $includeSecrets Also count events the reader does not know yet and events never told.
     * @param int      $limit          How many linked entries to return (1 to 50).
     *
     * @return array<string, mixed>
     */
    public function get_related(string $id, ?int $atChapter = null, ?int $beforeChapter = null, bool $includeSecrets = false, int $limit = 20): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new ToolCallException('The limit must be between 1 and 50.');
        }

        return $this->call(fn () => $this->related->related(
            $this->book,
            $id,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
            $limit,
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
        $this->checkPage($limit, $offset);

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
        return $this->withoutDates($this->call(fn () => $this->timeline->event(
            $this->book,
            $id,
            $this->viewpoint($atChapter, $beforeChapter, $includeSecrets),
        )));
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

    private function checkPage(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > self::MAX_EVENTS || $offset < 0) {
            throw new ToolCallException(\sprintf('The limit must be between 1 and %d and the offset 0 or more.', self::MAX_EVENTS));
        }
    }

    /**
     * The creation and update dates of a record tell an AI nothing about the story: leave them out.
     *
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function withoutDates(array $record): array
    {
        return array_diff_key($record, ['createdAt' => 0, 'updatedAt' => 0]);
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
