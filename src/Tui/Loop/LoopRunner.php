<?php

declare(strict_types=1);

namespace App\Tui\Loop;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class LoopRunner
{
    private iterable $providers;

    /**
     * @param iterable<TimerProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.tui.loop.timer_provider')] iterable $providers,
        private Scheduler $scheduler = new Scheduler(),
    ) {
        $this->providers = $providers;
    }

    public function boot(): void
    {
        foreach ($this->providers as $p) {
            $p->register($this->scheduler);
        }
    }

    public function tick(): void
    {
        $this->scheduler->tick();
    }

    public function sleep(int $minFloorMs = 1, int $maxCeilMs = 16): void
    {
        $this->scheduler->sleepUntilNextDue($minFloorMs, $maxCeilMs);
    }
}
