<?php

declare(strict_types=1);

namespace App\Tui\Loop;

interface TimerProviderInterface
{
    public function register(Scheduler $scheduler): void;
}
