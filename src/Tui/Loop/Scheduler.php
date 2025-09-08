<?php

declare(strict_types=1);

namespace App\Tui\Loop;

final class Scheduler
{
    /** @var list<Timer> */
    private array $timers = [];

    public function addPeriodic(int $intervalMs, \Closure $callback): void
    {
        $now = self::nowMs();
        $this->timers[] = new Timer($intervalMs, $callback, $now + $intervalMs);
    }

    public function tick(): void
    {
        $now = self::nowMs();
        foreach ($this->timers as $t) {
            $t->runIfDue($now);
        }
    }

    public function sleepUntilNextDue(int $minFloorMs = 1, int $maxCeilMs = 16): void
    {
        if (!$this->timers) {
            usleep($minFloorMs * 1_000);

            return;
        }
        $now = self::nowMs();
        $next = \PHP_INT_MAX;
        foreach ($this->timers as $t) {
            $next = min($next, $t->nextDueInMs($now));
        }
        $sleep = min($maxCeilMs, max($minFloorMs, $next));
        if ($sleep > 0) {
            usleep($sleep * 1_000);
        }
    }

    public static function nowMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }
}
