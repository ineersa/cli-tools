<?php

namespace App\Tui\Command;

use App\Tui\Exception\ProblemException;

class ToolsCommand implements CommandInterface
{
    public function supports(string $command): bool
    {
        return '/tools' === trim($command);
    }

    public function execute(string $command): never
    {
        throw new ProblemException('Not implemented');
    }
}
