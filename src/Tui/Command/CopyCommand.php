<?php

namespace App\Tui\Command;

use App\Tui\Component\ContentItemFactory;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use App\Tui\Utility\Clipboard;
use Psr\Log\LoggerInterface;

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

    public function execute(string $command): void
    {
        $contentItems = $this->state->getContentItems();
        for($i = count($contentItems)-1; $i >= 0; $i--) {
            if ($contentItems[$i]->type === ContentItemFactory::RESPONSE_CARD) {
                Clipboard::copy($contentItems[$i]->originalString);
                return;
            }
        }
        throw new ProblemException('No response found to copy.');
    }
}
