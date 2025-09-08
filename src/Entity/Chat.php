<?php

declare(strict_types=1);

namespace App\Entity;

use App\Agent\Mode;
use App\Repository\ChatRepository;
use App\Service\Chat\ChatStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ChatRepository::class)]
#[ORM\Index(name: 'chat_status_mode_idx', columns: ['status', 'mode'])]
class Chat
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(enumType: Mode::class)]
    private ?Mode $mode = null;

    #[ORM\Column(enumType: ChatStatus::class)]
    private ?ChatStatus $status = null;

    #[ORM\ManyToOne(inversedBy: 'chats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    /**
     * @var Collection<int, ChatTurn>
     */
    #[ORM\OneToMany(targetEntity: ChatTurn::class, mappedBy: 'chat', orphanRemoval: true)]
    private Collection $chatTurns;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?ChatTurn $last_turn = null;

    public function __construct()
    {
        $this->chatTurns = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getMode(): ?Mode
    {
        return $this->mode;
    }

    public function setMode(Mode $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getStatus(): ?ChatStatus
    {
        return $this->status;
    }

    public function setStatus(ChatStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, ChatTurn>
     */
    public function getChatTurns(): Collection
    {
        return $this->chatTurns;
    }

    public function addChatTurn(ChatTurn $chatTurn): static
    {
        if (!$this->chatTurns->contains($chatTurn)) {
            $this->chatTurns->add($chatTurn);
            $chatTurn->setChat($this);
        }

        return $this;
    }

    public function removeChatTurn(ChatTurn $chatTurn): static
    {
        if ($this->chatTurns->removeElement($chatTurn)) {
            // set the owning side to null (unless already changed)
            if ($chatTurn->getChat() === $this) {
                $chatTurn->setChat(null);
            }
        }

        return $this;
    }

    public function getLastTurn(): ?ChatTurn
    {
        return $this->last_turn;
    }

    public function setLastTurn(?ChatTurn $last_turn): static
    {
        $this->last_turn = $last_turn;

        return $this;
    }
}
