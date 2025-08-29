<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\ExitInterruptException;

class ExitCommand implements CommandInterface
{
    public function supports(string $command): bool
    {
        return '/exit' === trim($command);
    }

    /**
     * @throws ExitInterruptException
     */
    public function execute(string $command): never
    {
        throw new ExitInterruptException();
    }
}
