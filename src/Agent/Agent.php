<?php

declare(strict_types=1);

namespace App\Agent;

class Agent
{
    private Mode $mode;
    public function __construct(
        private string $model,
    ) {
        $this->mode = Mode::getDefaultMode();
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setMode(Mode $mode): Agent
    {
        $this->mode = $mode;
        return $this;
    }

    public function setModel(string $model): Agent
    {
        $this->model = $model;
        return $this;
    }


}
