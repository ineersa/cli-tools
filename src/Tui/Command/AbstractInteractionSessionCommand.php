<?php

namespace App\Tui\Command;

use App\Tui\Application;
use App\Tui\Component\StepComponent;
use App\Tui\DTO\StepComponentDTO;
use App\Tui\State;

abstract class AbstractInteractionSessionCommand implements InteractionSessionInterface
{
    protected int $step = 1;

    public function __construct(
        private State $state,
        private Application $application,
    )
    {
    }

    protected function addStepComponent(StepComponentDTO $dto): void
    {
        $step = new StepComponent($dto, $this->state);
        $this->state->setDynamicIslandComponents([
            $step,
        ]);
    }

    public function cancel(): void
    {
        $this->state->setInteractionSession(null);
    }
}
