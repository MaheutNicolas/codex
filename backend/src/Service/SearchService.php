<?php

namespace App\Service;

use App\Api\Viewpoint;
use App\Entity\Book;
use App\Repository\EventRepository;
use App\Repository\KnowledgeRepository;

/**
 * Full-text search over the entries and the events a reader may see. The best matches come first:
 * a word found in a name, identifier, alias or tag beats a word found in the texts.
 */
final class SearchService
{
    public function __construct(
        private readonly KnowledgeRepository $knowledge,
        private readonly EventRepository $events,
    ) {
    }

    /**
     * An entry has a "name", an event a "title" (as everywhere else in the API).
     *
     * @param list<string> $terms the words of the search (letters and digits only)
     *
     * @return array{data: list<array<string, mixed>>, total: int} "total" counts every match, not only the returned ones
     */
    public function search(Book $book, Viewpoint $viewpoint, array $terms, int $limit): array
    {
        // Very short words ("de", "a") would match nearly everything: they only count when the search has no longer word.
        $words = array_filter($terms, static fn (string $term) => mb_strlen($term) >= 3) ?: $terms;
        // Full-text: any of the words, each also matching longer words that begin with it ("alar" finds "alarm").
        $match = implode(' ', array_map(static fn (string $term) => $term.'*', $words));
        // Plain match on names, identifiers, aliases and tags, word by word (finds nicknames and short words).
        $likes = array_values(array_map(static fn (string $term) => '%'.addcslashes($term, '%_\\').'%', $words));

        $knowledge = $this->knowledge->search($book, $match, $likes, $limit);
        $events = $this->events->search($book, $viewpoint, $match, $likes, $limit);

        $results = [];
        foreach ($knowledge['rows'] as $row) {
            $results[] = [
                'kind' => 'knowledge',
                'id' => $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'summary' => $row['summary'],
                'named' => (int) $row['named'],
                'score' => (float) $row['score'],
            ];
        }
        foreach ($events['rows'] as $row) {
            $results[] = [
                'kind' => 'event',
                'id' => $row['id'],
                'title' => $row['title'],
                'chapter' => null === $row['chapter'] ? null : (int) $row['chapter'],
                'worldOrder' => (int) $row['worldOrder'],
                'summary' => $row['summary'],
                'named' => (int) $row['named'],
                'score' => (float) $row['score'],
            ];
        }

        usort($results, static fn (array $a, array $b) => [$b['named'], $b['score']] <=> [$a['named'], $a['score']]);

        return [
            'data' => array_map(static function (array $result) {
                unset($result['named']);
                $result['score'] = round($result['score'], 3);

                return $result;
            }, \array_slice($results, 0, $limit)),
            'total' => $knowledge['total'] + $events['total'],
        ];
    }
}
