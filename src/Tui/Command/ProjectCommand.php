<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Command\Project\ProjectChangeCommand;
use App\Tui\Command\Project\ProjectCreateCommand;
use App\Tui\Command\Project\ProjectDeleteCommand;
use App\Tui\Command\Project\ProjectEditCommand;
use App\Tui\Command\Project\ProjectListCommand;
use App\Tui\Exception\ProblemException;
use App\Tui\State;

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
        if ($command === '') {
            return false;
        }
        $tokens = preg_split('/\s+/', $command);

        if (!$tokens || strtolower($tokens[0]) !== '/project') {
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
        if (count($tokens) === 1) {
            throw new ProblemException($hint);
        }

        $sub = strtolower($tokens[1]);

        $isId = static function (?string $token): bool {
            if ($token === null) {
                return false;
            }
            if (!str_starts_with($token, '#')) {
                return false;
            }
            $token = ltrim($token, '#');
            return $token !== '' && ctype_digit($token);
        };

        return match ($sub) {
            'list' => count($tokens) === 2 && $tokens[1] === 'list',
            'create' => count($tokens) === 2 && $tokens[1] === 'create',
            'delete' => (count($tokens) === 3 && $isId($tokens[2]) && $tokens[1] === 'delete'),
            'edit'   => (count($tokens) === 3 && $isId($tokens[2]) && $tokens[1] === 'edit'),
            'change'   => (count($tokens) === 3 && $isId($tokens[2]) && $tokens[1] === 'change'),
            default  => throw new ProblemException($hint),
        };
    }

    public function execute(string $command): never
    {
        $tokens = preg_split('/\s+/', $command);
        if ($tokens[1] === 'create') {
            $this->projectCreateCommand->sendInitialMessage();
        }
        if ($tokens[1] === 'list') {
            $this->projectListCommand->list();
        }
        if ($tokens[1] === 'delete') {
            $this->projectDeleteCommand
                ->delete((int)ltrim($tokens[2], '#'));
        }
        if ($tokens[1] === 'change') {
            $this->projectChangeCommand
                ->changeTo((int)ltrim($tokens[2], '#'));
        }
        if ($tokens[1] === 'edit') {
            $this->projectEditCommand
                ->setId((int)ltrim($tokens[2], '#'))
                ->sendInitialMessage();
        }
        throw new ProblemException($command . ' not implemented yet');
    }
}
