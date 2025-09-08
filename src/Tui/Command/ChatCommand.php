<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Command\Chat\ChatClearAllCommand;
use App\Tui\Command\Chat\ChatDeleteCommand;
use App\Tui\Command\Chat\ChatListCommand;
use App\Tui\Command\Chat\ChatRestoreCommand;
use App\Tui\Command\Chat\ChatSummaryCommand;
use App\Tui\Exception\ProblemException;

class ChatCommand implements CommandInterface
{
    public function __construct(
        private readonly ChatListCommand $chatListCommand,
        private readonly ChatDeleteCommand $chatDeleteCommand,
        private readonly ChatRestoreCommand $chatRestoreCommand,
        private readonly ChatSummaryCommand $chatSummaryCommand,
        private readonly ChatClearAllCommand $chatClearAllCommand,
    ) {
    }

    public function supports(string $command): bool
    {
        $command = trim($command);
        if ('' === $command) {
            return false;
        }
        $tokens = preg_split('/\s+/', $command);

        if (!$tokens || '/chat' !== strtolower($tokens[0])) {
            return false;
        }

        $hint = <<<HINT
Commands available:
'list' => 'show list of chats',
'delete #' => 'delete chat #123',
'restore #' => 'restore chat #123 conversation',
'summary #' => 'show chat #123 summary',
'clear-all' => 'delete all chats, if you want to start new chat use /clear'
HINT;

        if (1 === \count($tokens)) {
            throw new ProblemException($hint);
        }

        $sub = strtolower($tokens[1]);

        $isId = static function (?string $token): bool {
            if (null === $token) {
                return false;
            }
            if (!str_starts_with($token, '#')) {
                return false;
            }
            $token = ltrim($token, '#');

            return '' !== $token && ctype_digit($token);
        };

        return match ($sub) {
            'list' => 2 === \count($tokens) && 'list' === $tokens[1],
            'delete' => (3 === \count($tokens) && $isId($tokens[2]) && 'delete' === $tokens[1]),
            'restore' => (3 === \count($tokens) && $isId($tokens[2]) && 'restore' === $tokens[1]),
            'summary' => (3 === \count($tokens) && $isId($tokens[2]) && 'summary' === $tokens[1]),
            'clear-all' => 2 === \count($tokens) && 'clear-all' === $tokens[1],
            default => throw new ProblemException($hint),
        };
    }

    public function execute(string $command): never
    {
        $tokens = preg_split('/\s+/', $command);
        if ('list' === $tokens[1]) {
            $this->chatListCommand->list();
        }
        if ('delete' === $tokens[1]) {
            $this->chatDeleteCommand->delete((int) ltrim($tokens[2], '#'));
        }
        if ('restore' === $tokens[1]) {
            $this->chatRestoreCommand->restore((int) ltrim($tokens[2], '#'));
        }
        if ('summary' === $tokens[1]) {
            $this->chatSummaryCommand->summary((int) ltrim($tokens[2], '#'));
        }
        if ('clear-all' === $tokens[1]) {
            $this->chatClearAllCommand->clearAll();
        }

        throw new ProblemException($command.' not implemented yet');
    }
}
