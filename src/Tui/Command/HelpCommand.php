<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Component\TextContentComponentItems;
use App\Tui\State;

class HelpCommand implements CommandInterface
{
    public function __construct(
        private State $state,
    ) {
    }

    public function supports(string $command): bool
    {
        return '/help' === trim($command);
    }

    public function execute(string $command): void
    {
        $this->state->pushContentItem(TextContentComponentItems::getHelp());
    }
}
