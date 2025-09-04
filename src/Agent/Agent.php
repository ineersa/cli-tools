<?php

declare(strict_types=1);

namespace App\Agent;

use App\Llm\LlmClient;
use App\Service\ProjectService;
use App\Tui\Exception\ProblemException;
use App\Worker\WorkerInterface;
use OpenAI;

class Agent
{
    private Mode $mode;
    private \App\Entity\Project $project;

    private array $activeWorkers = [];

    /**
     * @throws \Exception
     */
    public function __construct(
        public readonly LlmClient $smallModel,
        public readonly LlmClient $largeModel,
        ProjectService $projectService,
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

    public function getProject(): \App\Entity\Project
    {
        return $this->project;
    }

    public function setProject(\App\Entity\Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getSmallModel(): string
    {
        return $this->smallModel->getModel();
    }


    // TODO run checks for is running on workers and just poll? remove attach/detach?
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
}
