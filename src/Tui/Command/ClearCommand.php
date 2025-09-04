<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Component\TextContentComponentItems;
use App\Tui\Exception\CompleteException;
use App\Tui\State;

class ClearCommand implements CommandInterface
{
    public function __construct(
        private State $state,
    ) {
    }

    public function supports(string $command): bool
    {
        return '/clear' === trim($command);
    }

    public function execute(string $command): never
    {
        $this->state->setContentItems([
            TextContentComponentItems::getLogo(),
        ]);

        throw new CompleteException("/clear \n Chat save and summary, and new chat start is not implemented yet.");
    }
}
