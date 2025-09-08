<?php

declare(strict_types=1);

namespace App\Worker;

interface WorkerInterface
{
    public function poll(string $requestId): void;

    public function stop(): void;

    public function isRunning(): bool;
}
