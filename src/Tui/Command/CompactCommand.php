<?php

namespace App\Tui\Command;

use App\Tui\Exception\ProblemException;

class CompactCommand implements CommandInterface
{
    public function supports(string $command): bool
    {
        return '/compact' === trim($command);
    }

    public function execute(string $command): never
    {
        throw new ProblemException('Not implemented');
    }
}
