<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\ProblemException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class Runner
{
    /** @var iterable<CommandInterface> */
    private iterable $commandsList;

    public function __construct(
        #[TaggedIterator('app.tui.command')] iterable $commandsList
    ) {
        $this->commandsList = $commandsList;
    }

    public function runCommand(string $commandString): void
    {
        foreach ($this->commandsList as $command) {
            if ($command->supports($commandString)) {
                $command->execute($commandString);
                return;
            }
        }
        throw new ProblemException('No supported commands found');
    }
}
