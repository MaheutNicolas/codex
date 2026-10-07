<?php

namespace App\Entity;

use App\Repository\KnowledgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The technical id never leaves the database: the API exposes the slug as "id",
 * unique within a book.
 */
#[ORM\Entity(repositoryClass: KnowledgeRepository::class)]
#[ORM\Table(name: 'knowledge')]
#[ORM\UniqueConstraint(name: 'uniq_knowledge_book_slug', columns: ['book_id', 'slug'])]
#[ORM\Index(name: 'idx_knowledge_book_type', columns: ['book_id', 'type'])]
#[ORM\HasLifecycleCallbacks]
class Knowledge
{
    public const TYPES = ['character', 'place', 'system'];

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

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'The type is required.')]
    #[Assert\Choice(choices: self::TYPES, message: 'The type must be one of: {{ choices }}.')]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'The name is required.')]
    #[Assert\Length(max: 255, maxMessage: 'The name cannot exceed {{ limit }} characters.')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'The summary is required.')]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Type('string', message: 'Each alias must be a string.')])]
    private array $aliases = [];

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /** @param list<string> $aliases */
    public function setAliases(array $aliases): static
    {
        $this->aliases = $aliases;

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
