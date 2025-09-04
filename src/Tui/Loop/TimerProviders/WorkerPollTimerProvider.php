<?php

namespace App\Tui\Loop\TimerProviders;

use App\Agent\Agent;
use App\Tui\Component\ProblemComponent;
use App\Tui\Exception\ProblemException;
use App\Tui\Loop\Scheduler;
use App\Tui\Loop\TimerProviderInterface;
use App\Tui\State;

final readonly class WorkerPollTimerProvider implements TimerProviderInterface
{
    public function __construct(
        private Agent $agent,
        private State $state,
    ) {}

    public function register(Scheduler $scheduler): void
    {
        // Poll every 250 ms
        $scheduler->addPeriodic(250, function (): void {
            try {
                $this->agent->pollWorkers();
            } catch (ProblemException $e) {
                $this->state->setDynamicIslandComponents([
                    ProblemComponent::NAME => new ProblemComponent($e, $this->state),
                ]);
            }
        });
    }
}
