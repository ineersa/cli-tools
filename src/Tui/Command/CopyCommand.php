<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Component\ContentItemFactory;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use App\Tui\Utility\Clipboard;

class CopyCommand implements CommandInterface
{
    public function __construct(
        private readonly State $state,
    ) {
    }

    public function supports(string $command): bool
    {
        return '/copy' === trim($command);
    }

    public function execute(string $command): never
    {
        $contentItems = $this->state->getContentItems();
        for ($i = \count($contentItems) - 1; $i >= 0; --$i) {
            if (ContentItemFactory::RESPONSE_CARD === $contentItems[$i]->type) {
                Clipboard::copy($contentItems[$i]->originalString);
                throw new CompleteException('/copy');
            }
        }
        throw new ProblemException('No response found to copy.');
    }
}
