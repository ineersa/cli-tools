<?php

namespace App\Tui\Loop;

final class Timer {
    public function __construct(
        public readonly int       $intervalMs,
        private readonly \Closure $callback,
        private int               $nextDueMs
    ) {}

    public function runIfDue(int $nowMs): void {
        if ($nowMs >= $this->nextDueMs) {
            ($this->callback)($nowMs);
            $this->nextDueMs = $nowMs + $this->intervalMs;
        }
    }

    public function nextDueInMs(int $nowMs): int {
        return max(0, $this->nextDueMs - $nowMs);
    }
}

