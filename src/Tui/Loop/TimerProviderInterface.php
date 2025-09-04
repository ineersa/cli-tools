<?php

namespace App\Tui\Loop;

interface TimerProviderInterface
{
    public function register(Scheduler $scheduler): void;
}
