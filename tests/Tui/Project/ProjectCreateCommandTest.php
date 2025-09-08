<?php

declare(strict_types=1);

namespace App\Tests\Tui\Project;

use App\Tui\Command\Project\ProjectCreateCommand;
use App\Tui\Component\StepComponent;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\FollowupException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectCreateCommandTest extends KernelTestCase
{
    public function testInitialMessageShowsStepAndStartsSession(): void
    {
        self::bootKernel();
        $command = $this->makeCommand();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            $this->assertInstanceOf(StepComponent::class, array_values($components)[0] ?? null);
            $this->assertSame($command, $state->getInteractionSession());
        }
    }

    public function testHappyFlowCreatesProjectAndEmitsSteps(): void
    {
        self::bootKernel();
        $command = $this->makeCommand();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);

        // 0) Initial message
        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            // StepComponent is keyed by numeric index per addStepComponent()
            $first = array_values($components)[0] ?? null;
            $this->assertInstanceOf(StepComponent::class, $first);
            $this->assertSame($command, $state->getInteractionSession());
        }

        // 1) Name step
        $uniqueName = 'proj-'.bin2hex(random_bytes(4));
        try {
            $command->step($uniqueName);
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            $this->assertInstanceOf(StepComponent::class, array_values($components)[0] ?? null);
        }

        // 2) Workdir step
        $tmpDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'pct_'.bin2hex(random_bytes(4));
        $this->assertTrue(@mkdir($tmpDir) || is_dir($tmpDir));
        try {
            $command->step($tmpDir);
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            $this->assertInstanceOf(StepComponent::class, array_values($components)[0] ?? null);
        }

        // 3) Default step ('y')
        try {
            $command->step('y');
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            $this->assertInstanceOf(StepComponent::class, array_values($components)[0] ?? null);
        }

        // 4) Instructions step
        $instructions = 'AGENTS.md';
        try {
            $command->step($instructions);
        } catch (FollowupException) {
            $components = $state->getDynamicIslandComponents();
            $this->assertNotEmpty($components);
            $this->assertInstanceOf(StepComponent::class, array_values($components)[0] ?? null);
        }

        // 5) Confirm step ('y') -> Complete
        $completeMessage = null;
        try {
            $command->step('y');
        } catch (CompleteException $exception) {
            $completeMessage = $exception->getMessage();
            $this->assertStringContainsString('/project create', $completeMessage);
            $this->assertStringContainsString('Project #', $completeMessage);
            $this->assertStringContainsString('has been created', $completeMessage);
        }

        // Verify entity persisted with the expected fields
        $em = $container->get('doctrine')->getManager();
        $repo = $em->getRepository(\App\Entity\Project::class);
        $created = $repo->findOneBy(['name' => $uniqueName]);
        $this->assertNotNull($created, 'Project should be created in DB');
        $this->assertSame($uniqueName, $created->getName());
        $this->assertSame(rtrim($tmpDir, '/'), rtrim((string) $created->getWorkdir(), '/'));
        $this->assertTrue((bool) $created->isDefault());
        $this->assertSame($instructions, $created->getInstructions());
    }

    private function makeCommand(): ProjectCreateCommand
    {
        $container = static::getContainer();
        $state = $container->get(State::class);
        $state->setEditing(true);
        $state->setDynamicIslandComponents([]);
        $state->setInteractionSession(null);
        $application = $container->get(\App\Tui\Application::class);
        $projectService = $container->get(\App\Service\ProjectService::class);

        return new ProjectCreateCommand($state, $application, $projectService);
    }
}
