<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\ProblemException;

class ProjectCommand implements CommandInterface
{
    public function supports(string $command): bool
    {
        return '/project' === trim($command);
    }

    public function execute(string $command): never
    {
        throw new ProblemException('Not implemented');
    }
}
