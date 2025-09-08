<?php

declare(strict_types=1);

namespace App\Agent;

use App\Entity\Chat;
use App\Entity\Project;
use App\Llm\LlmClient;
use App\Service\ChatService;
use App\Service\ProjectService;
use App\Tui\Exception\ProblemException;
use App\Worker\WorkerInterface;
use http\Exception\RuntimeException;
use OpenAI;

class Agent
{
    private Mode $mode;
    private ?Project $project;

    private array $activeWorkers = [];

    private array $consumers = [];

    private ?Chat $activeChat = null;

    /**
     * @throws \Exception
     */
    public function __construct(
        public readonly LlmClient $smallModel,
        public readonly LlmClient $largeModel,
        ProjectService            $projectService,
        private ChatService               $chatService,
    ) {
        $this->mode = Mode::getDefaultMode();
        $this->project = $projectService->getDefaultProject();
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }

    public function getModel(): string
    {
        return $this->largeModel->getModel();
    }

    public function setMode(Mode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getSmallModel(): string
    {
        return $this->smallModel->getModel();
    }


    public function pollWorkers(): void
    {
        foreach ($this->activeWorkers as $requestId => $worker) {
            $worker->poll($requestId);
        }
    }

    public function detachWorker(string $requestId): void
    {
        if (isset($this->activeWorkers[$requestId])) {
            unset($this->activeWorkers[$requestId]);
        }
    }

    public function attachWorker(string $requestId, WorkerInterface $worker): void
    {
        if (isset($this->activeWorkers[$requestId])) {
            throw new ProblemException('Already attached worker');
        }
        $this->activeWorkers[$requestId] = $worker;
    }

    public function pollConsumers(): void
    {
        foreach ($this->consumers as $requestId => $worker) {
            $worker->poll($requestId);
        }
    }

    public function detachConsumer(string $requestId): void
    {
        if (isset($this->consumers[$requestId])) {
            unset($this->consumers[$requestId]);
        }
    }

    public function attachConsumer(string $requestId, WorkerInterface $worker): void
    {
        if (isset($this->consumers[$requestId])) {
            throw new ProblemException('Already attached consumer with requestId = '. $requestId);
        }
        $this->consumers[$requestId] = $worker;
    }

    public function getActiveChat(): ?Chat
    {
        return $this->activeChat;
    }

    public function setActiveChat(): self
    {
        if (!$this->getProject()) {
            return $this;
        }

        $this->activeChat = $this->chatService
            ->getOpenChat(
                $this->getProject()->getId(),
                $this->getMode()
            );

        return $this;
    }

    public function cleanUp(): void
    {
        $openChat = $this->chatService
            ->getOpenChat(
                $this->getProject()->getId(),
                $this->getMode()
            );
        if ($openChat) {
            $this->chatService
                ->resetOpenChat($openChat);
        }

        $this->activeChat = null;
    }
}
