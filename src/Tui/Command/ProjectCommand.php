<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Command\Project\ProjectChangeCommand;
use App\Tui\Command\Project\ProjectCreateCommand;
use App\Tui\Command\Project\ProjectDeleteCommand;
use App\Tui\Command\Project\ProjectEditCommand;
use App\Tui\Command\Project\ProjectListCommand;
use App\Tui\Exception\ProblemException;

class ProjectCommand implements CommandInterface
{
    public function __construct(
        private ProjectCreateCommand $projectCreateCommand,
        private ProjectListCommand $projectListCommand,
        private ProjectDeleteCommand $projectDeleteCommand,
        private ProjectEditCommand $projectEditCommand,
        private ProjectChangeCommand $projectChangeCommand,
    ) {
    }

    public function supports(string $command): bool
    {
        $command = trim($command);
        if ('' === $command) {
            return false;
        }
        $tokens = preg_split('/\s+/', $command);

        if (!$tokens || '/project' !== strtolower($tokens[0])) {
            return false;
        }

        $hint = <<<HINT
Commands available:
'list' => 'list projects',
'delete #' => 'delete project #123',
'create' => 'create new project',
'edit #ID' => 'edit project #123, fields: name, workdir, default, instructions',
'change #ID => 'change current project to project #123'
HINT;
        // TODO refactor it to pass and throw in execute
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
            'create' => 2 === \count($tokens) && 'create' === $tokens[1],
            'delete' => (3 === \count($tokens) && $isId($tokens[2]) && 'delete' === $tokens[1]),
            'edit' => (3 === \count($tokens) && $isId($tokens[2]) && 'edit' === $tokens[1]),
            'change' => (3 === \count($tokens) && $isId($tokens[2]) && 'change' === $tokens[1]),
            default => throw new ProblemException($hint),
        };
    }

    public function execute(string $command): never
    {
        $tokens = preg_split('/\s+/', $command);
        if ('create' === $tokens[1]) {
            $this->projectCreateCommand->sendInitialMessage();
        }
        if ('list' === $tokens[1]) {
            $this->projectListCommand->list();
        }
        if ('delete' === $tokens[1]) {
            $this->projectDeleteCommand
                ->delete((int) ltrim($tokens[2], '#'));
        }
        if ('change' === $tokens[1]) {
            $this->projectChangeCommand
                ->changeTo((int) ltrim($tokens[2], '#'));
        }
        if ('edit' === $tokens[1]) {
            $this->projectEditCommand
                ->setId((int) ltrim($tokens[2], '#'))
                ->sendInitialMessage();
        }
        throw new ProblemException($command.' not implemented yet');
    }
}
