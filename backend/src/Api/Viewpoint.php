<?php

namespace App\Api;

/**
 * What the reader of the AI is allowed to see: events told up to a chapter, and whether the secrets
 * of the author (events the reader does not know, told off-screen) are included.
 */
final readonly class Viewpoint
{
    /**
     * @param int|null $maxChapter the last chapter the reader has read (inclusive); null: no limit
     */
    public function __construct(
        public ?int $maxChapter = null,
        public bool $includeSecrets = false,
    ) {
    }

    /** Whether an event is visible from this point of view (the same rule as the timeline and search queries). */
    public function allows(bool $revealed, ?int $chapter): bool
    {
        if (!$this->includeSecrets && !$revealed) {
            return false;
        }
        if (null !== $this->maxChapter) {
            return null === $chapter ? $this->includeSecrets : $chapter <= $this->maxChapter;
        }

        return true;
    }
}
