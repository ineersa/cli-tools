<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\ProblemException;

class ChatCommand implements CommandInterface
{
    public function supports(string $command): bool
    {
        return '/chat' === trim($command);
    }

    public function execute(string $command): never
    {
        throw new ProblemException('Not implemented');
    }
}
