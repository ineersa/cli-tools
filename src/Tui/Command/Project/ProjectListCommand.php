<?php

namespace App\Tui\Command\Project;

use App\Service\ProjectService;
use App\Tui\Component\TableListComponent;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Symfony\Component\Serializer\SerializerInterface;

class ProjectListCommand
{
    public function __construct(
        private ProjectService $projectService,
        private State $state,
        private SerializerInterface $serializer,
    )
    {
    }

    public function list(): never
    {
        $projects = $this->projectService->projectRepository
            ->findAll();
        if (empty($projects)) {
            throw new CompleteException("/projects list \n No projects were found");
        }

        $data = json_decode($this->serializer->serialize($projects, 'json'), true);
        $component = new TableListComponent(
            $this->state,
            $data
        );
        $this->state->setDynamicIslandComponents([
            TableListComponent::NAME => $component,
        ]);
        // we overtake controls for table
        $this->state->setEditing(false);

        throw new CompleteException("/projects list \n Found " . count($projects) . " projects");
    }
}
