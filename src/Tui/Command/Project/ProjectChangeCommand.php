<?php

declare(strict_types=1);

namespace App\Tui\Command\Project;

use App\Service\ProjectService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;

class ProjectChangeCommand
{
    public function __construct(
        private ProjectService $projectService, private readonly State $state,
    ) {
    }

    public function changeTo(int $id): never
    {
        $entity = $this->projectService->projectRepository
            ->find($id);
        if (!$entity) {
            throw new ProblemException('Project not found');
        }
        $this->state->setProject($entity);

        throw new CompleteException("/project change \n Project changed to #".$id.'.');
    }
}
