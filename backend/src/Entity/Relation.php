<?php

namespace App\Entity;

use App\Repository\RelationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The state of the relation between two entries, from a chapter on. A relation that changes is not edited: a new
 * state is added at the chapter where it changes (allies in chapter 1, enemies in chapter 6...), and the state of a
 * pair at a given chapter is the latest one up to that chapter. The history of a pair is the list of its states.
 * Like for knowledge entries and events, the technical id never leaves the database: the API exposes the slug as "id".
 */
#[ORM\Entity(repositoryClass: RelationRepository::class)]
#[ORM\Table(name: 'knowledge_relation')]
#[ORM\UniqueConstraint(name: 'uniq_relation_book_slug', columns: ['book_id', 'slug'])]
#[ORM\UniqueConstraint(name: 'uniq_relation_pair_chapter', columns: ['source_id', 'target_id', 'chapter'])]
#[ORM\Index(name: 'idx_relation_target', columns: ['target_id'])]
#[ORM\Index(name: 'idx_relation_book_chapter', columns: ['book_id', 'chapter'])]
#[ORM\HasLifecycleCallbacks]
class Relation
{
    /**
     * The types of entries that can be in a relation: the ones that act in the story. A place, an item or a concept
     * is linked to the story by the events it takes part in. (Existing relations with other types stay readable.)
     */
    public const ENTRY_TYPES = ['character', 'group', 'species'];

    /** A relation that goes both ways. */
    public const SYMMETRIC_TYPES = ['ally', 'enemy', 'rival', 'friend', 'family', 'partner', 'other', 'none'];

    /** A relation that reads differently from each side: the source is the mentor of the target, the parent of it... */
    public const DIRECTED_TYPES = ['mentor', 'parent', 'member_of', 'leader_of', 'serves'];

    /** "none" ends the relation of a pair: from that chapter on, they are no longer linked. */
    public const TYPES = ['ally', 'enemy', 'rival', 'friend', 'family', 'partner', 'mentor', 'parent', 'member_of', 'leader_of', 'serves', 'other', 'none'];

    /** The chapter stored for a state that holds from the start of the book. */
    public const FROM_THE_START = 0;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Book $book = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'The identifier is required.')]
    #[Assert\Length(max: 100, maxMessage: 'The identifier cannot exceed {{ limit }} characters.')]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'The identifier must be a slug (lowercase letters, digits and hyphens).')]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Knowledge::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'The source entry is required.')]
    private ?Knowledge $source = null;

    #[ORM\ManyToOne(targetEntity: Knowledge::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'The target entry is required.')]
    private ?Knowledge $target = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'The type is required.')]
    #[Assert\Choice(choices: self::TYPES, message: 'The type must be one of: {{ choices }}.')]
    private ?string $type = null;

    /** 0: from the start of the book (the API says null). */
    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 2147483647, notInRangeMessage: 'The chapter must be between {{ min }} and {{ max }}.')]
    private int $chapter = self::FROM_THE_START;

    /** False for a relation the reader does not know yet (a secret ally, a hidden parent...). */
    #[ORM\Column(options: ['default' => true])]
    private bool $revealed = true;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'The note cannot exceed {{ limit }} characters.')]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function isSymmetric(string $type): bool
    {
        return \in_array($type, self::SYMMETRIC_TYPES, true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSource(): ?Knowledge
    {
        return $this->source;
    }

    public function setSource(Knowledge $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTarget(): ?Knowledge
    {
        return $this->target;
    }

    public function setTarget(Knowledge $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getChapter(): int
    {
        return $this->chapter;
    }

    public function setChapter(int $chapter): static
    {
        $this->chapter = $chapter;

        return $this;
    }

    public function isRevealed(): bool
    {
        return $this->revealed;
    }

    public function setRevealed(bool $revealed): static
    {
        $this->revealed = $revealed;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
