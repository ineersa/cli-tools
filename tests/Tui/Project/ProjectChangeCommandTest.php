<?php

declare(strict_types=1);

namespace App\Tests\Tui\Project;

use App\Agent\Agent;
use App\Entity\Project;
use App\Service\ChatService;
use App\Service\ProjectService;
use App\Tui\Command\Project\ProjectChangeCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectChangeCommandTest extends KernelTestCase
{
    public function testChangeToExistingProjectUpdatesStateCleansChatAndCompletes(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ProjectService $projectService */
        $projectService = $container->get(ProjectService::class);
        /** @var State $state */
        $state = $container->get(State::class);
        /** @var Agent $agent */
        $agent = $container->get(Agent::class);
        /** @var ChatService $chatService */
        $chatService = $container->get(ChatService::class);

        // Ensure we have a known project from fixtures
        $project = $projectService->projectRepository->findOneBy(['name' => 'demo']);
        $this->assertInstanceOf(Project::class, $project, 'Fixture project "demo" must exist');

        // Create or get an open chat for the current agent mode and project to observe cleanup
        // First ensure agent uses some project (may be default) and then switch will reset chat
        $agent->setProject($project);
        $agent->setActiveChat(); // open chat based on current project + mode (if any exists)

        // Now invoke the command to change to the same project's id (still should cleanup and throw CompleteException)
        $command = new ProjectChangeCommand($projectService, $state, $agent);

        try {
            $command->changeTo($project->getId());
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/project change', $e->getMessage());
            $this->assertStringContainsString('Project changed to #'.$project->getId(), $e->getMessage());
        }

        // Verify state updated
        $this->assertNotNull($state->getProject());
        $this->assertSame($project->getId(), $state->getProject()->getId());

        // Verify chat has been cleaned up: there should be no active chat in agent after cleanup
        $this->assertNull($agent->getActiveChat(), 'Active chat should be null after cleanup');

        // Additionally, ensure there is no lingering open chat for the project+mode combination
        $open = $chatService->getOpenChat($project->getId(), $agent->getMode());
        $this->assertNull($open, 'Open chat should be reset by cleanUpChat');
    }

    public function testChangeToMissingProjectThrowsProblemException(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ProjectService $projectService */
        $projectService = $container->get(ProjectService::class);
        /** @var State $state */
        $state = $container->get(State::class);
        /** @var Agent $agent */
        $agent = $container->get(Agent::class);

        $command = new ProjectChangeCommand($projectService, $state, $agent);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Project not found');
        $command->changeTo(999999);
    }
}
