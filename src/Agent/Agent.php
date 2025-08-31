<?php

declare(strict_types=1);

namespace App\Agent;


use App\Service\ProjectService;

class Agent
{
    private Mode $mode;
    private \App\Entity\Project $project;

    /**
     * @throws \Exception
     */
    public function __construct(
        private string $model,
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
        return $this->model;
    }

    public function setMode(Mode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

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
}
