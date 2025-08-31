<?php

namespace App\Tui\Command\Project;

use App\Service\ProjectService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;

class ProjectDeleteCommand
{
    public function __construct(
        private ProjectService $projectService,
    ) {

    }

    public function delete(int $id): never
    {
        $entity = $this->projectService->projectRepository
            ->find($id);
        if (!$entity) {
            throw new ProblemException('Project not found');
        }
        $this->projectService->delete($entity);
        throw new CompleteException("/project delete \n Project #" . $id . " was successfully deleted.");
    }
}
