<?php

namespace App\Entity;

use App\Repository\EventParticipantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EventParticipantRepository::class)]
#[ORM\Table(name: 'event_participant')]
#[ORM\Index(name: 'idx_event_participant_knowledge_event', columns: ['knowledge_id', 'event_id'])]
class EventParticipant
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'The event is required.')]
    private ?Event $event = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Knowledge::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'The knowledge entry is required.')]
    private ?Knowledge $knowledge = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'The role cannot exceed {{ limit }} characters.')]
    private ?string $role = null;

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

        return $this;
    }

    public function getKnowledge(): ?Knowledge
    {
        return $this->knowledge;
    }

    public function setKnowledge(Knowledge $knowledge): static
    {
        $this->knowledge = $knowledge;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;

        return $this;
    }
}
