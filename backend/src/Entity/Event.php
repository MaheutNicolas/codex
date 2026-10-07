<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The technical id never leaves the database: the API exposes the slug as "id",
 * unique within a book.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\UniqueConstraint(name: 'uniq_event_book_slug', columns: ['book_id', 'slug'])]
#[ORM\Index(name: 'idx_event_book_chapter_world_order', columns: ['book_id', 'chapter', 'world_order'])]
#[ORM\Index(name: 'idx_event_book_world_order', columns: ['book_id', 'world_order'])]
#[ORM\HasLifecycleCallbacks]
class Event
{
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

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'The title is required.')]
    #[Assert\Length(max: 255, maxMessage: 'The title cannot exceed {{ limit }} characters.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'The summary is required.')]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'The world order is required.')]
    #[Assert\Range(min: -2147483648, max: 2147483647, notInRangeMessage: 'The world order must be between {{ min }} and {{ max }}.')]
    private ?int $worldOrder = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'The date cannot exceed {{ limit }} characters.')]
    private ?string $worldDate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 2147483647, notInRangeMessage: 'The chapter must be between {{ min }} and {{ max }}.')]
    private ?int $chapter = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $revealed = true;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Type('string', message: 'Each tag must be a string.')])]
    private array $tags = [];

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    public function getWorldOrder(): ?int
    {
        return $this->worldOrder;
    }

    public function setWorldOrder(int $worldOrder): static
    {
        $this->worldOrder = $worldOrder;

        return $this;
    }

    public function getWorldDate(): ?string
    {
        return $this->worldDate;
    }

    public function setWorldDate(?string $worldDate): static
    {
        $this->worldDate = $worldDate;

        return $this;
    }

    public function getChapter(): ?int
    {
        return $this->chapter;
    }

    public function setChapter(?int $chapter): static
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

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

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
