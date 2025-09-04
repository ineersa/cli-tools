<?php

namespace App\Entity;

use App\Repository\ChatTurnRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use \App\Entity\Chat;
use \App\Service\Chat\ChatTurnType;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ChatTurnRepository::class)]
#[ORM\Index(name: "chat_turn_idx_idx", columns: ["idx"])]
#[ORM\Index(name: "chat_turn_request_id_idx", columns: ["request_id"])]
class ChatTurn
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'chatTurns')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Chat $chat = null;

    #[ORM\Column]
    private ?int $idx = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $context = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary_snapshot = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $finish_reason = null;

    #[ORM\Column(nullable: true)]
    private ?int $prompt_tokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $completion_tokens = null;

    #[ORM\Column(nullable: true)]
    private ?int $total_tokens = null;

    #[ORM\Column(enumType: ChatTurnType::class)]
    private ?ChatTurnType $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $request_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChat(): ?Chat
    {
        return $this->chat;
    }

    public function setChat(?Chat $chat): static
    {
        $this->chat = $chat;

        return $this;
    }

    public function getIdx(): ?int
    {
        return $this->idx;
    }

    public function setIdx(int $idx): static
    {
        $this->idx = $idx;

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): static
    {
        $this->context = $context;

        return $this;
    }

    public function getSummarySnapshot(): ?string
    {
        return $this->summary_snapshot;
    }

    public function setSummarySnapshot(?string $summary_snapshot): static
    {
        $this->summary_snapshot = $summary_snapshot;

        return $this;
    }

    public function getFinishReason(): ?string
    {
        return $this->finish_reason;
    }

    public function setFinishReason(?string $finish_reason): static
    {
        $this->finish_reason = $finish_reason;

        return $this;
    }

    public function getPromptTokens(): ?int
    {
        return $this->prompt_tokens;
    }

    public function setPromptTokens(?int $prompt_tokens): static
    {
        $this->prompt_tokens = $prompt_tokens;

        return $this;
    }

    public function getCompletionTokens(): ?int
    {
        return $this->completion_tokens;
    }

    public function setCompletionTokens(?int $completion_tokens): static
    {
        $this->completion_tokens = $completion_tokens;

        return $this;
    }

    public function getTotalTokens(): ?int
    {
        return $this->total_tokens;
    }

    public function setTotalTokens(?int $total_tokens): static
    {
        $this->total_tokens = $total_tokens;

        return $this;
    }

    public function getType(): ?ChatTurnType
    {
        return $this->type;
    }

    public function setType(ChatTurnType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->request_id;
    }

    public function setRequestId(?string $request_id): static
    {
        $this->request_id = $request_id;

        return $this;
    }
}
