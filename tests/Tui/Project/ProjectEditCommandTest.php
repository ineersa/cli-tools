<?php

declare(strict_types=1);

namespace App\Tests\Tui\Project;

use App\Entity\Project;
use App\Tui\Command\Project\ProjectEditCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\FollowupException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectEditCommandTest extends KernelTestCase
{
    public function testInitialMessageAndAttributeSelectionTransitions(): void
    {
        $project = $this->bootAndGetProject();
        $command = $this->makeCommand($project);

        // Initial step should prompt for attribute and throw FollowupException
        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
            // ok
        }

        // Choose attribute 'name' and expect another FollowupException (step 2 prompt)
        try {
            $command->step('name');
        } catch (FollowupException) {
            // ok
        }
    }

    public function testHappyFlowEditName(): void
    {
        $project = $this->bootAndGetProject();
        $originalId = $project->getId();
        $command = $this->makeCommand($project);

        // Start interaction
        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
        }
        // Select attribute
        try {
            $command->step('name');
        } catch (FollowupException) {
        }
        // Provide new unique valid name
        $newName = 'demo-edited-'.uniqid('', false);
        try {
            $command->step($newName);
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/project edit', $e->getMessage());
            $this->assertStringContainsString('successfully updated', $e->getMessage());
        }

        // Assert persisted
        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $reloaded */
        $reloaded = $em->getRepository(Project::class)->find($originalId);
        $this->assertSame($newName, $reloaded->getName());
    }

    public function testHappyFlowEditWorkdir(): void
    {
        $project = $this->bootAndGetProject();
        $command = $this->makeCommand($project);

        // Prepare a valid directory (use system temp dir)
        $dir = sys_get_temp_dir();

        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
        }
        try {
            $command->step('workdir');
        } catch (FollowupException) {
        }

        try {
            $command->step($dir);
        } catch (CompleteException) {
            // ok
        }

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $reloaded */
        $reloaded = $em->getRepository(Project::class)->find($project->getId());
        $this->assertSame(rtrim($dir, '/'), $reloaded->getWorkdir());
    }

    public function testHappyFlowEditIsDefault(): void
    {
        $project = $this->bootAndGetProject();
        $command = $this->makeCommand($project);

        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
        }
        try {
            $command->step('is_default');
        } catch (FollowupException) {
        }

        try {
            $command->step('y');
        } catch (CompleteException) {
            // ok
        }

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $reloaded */
        $reloaded = $em->getRepository(Project::class)->find($project->getId());
        $this->assertTrue($reloaded->isDefault());
    }

    public function testHappyFlowEditInstructions(): void
    {
        $project = $this->bootAndGetProject();
        $command = $this->makeCommand($project);

        try {
            $command->sendInitialMessage();
        } catch (FollowupException) {
        }
        try {
            $command->step('instructions');
        } catch (FollowupException) {
        }

        $value = 'AGENTS.md';
        try {
            $command->step($value);
            $this->fail('Expected CompleteException after updating instructions');
        } catch (CompleteException) {
            // ok
        }

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $reloaded */
        $reloaded = $em->getRepository(Project::class)->find($project->getId());
        $this->assertSame($value, $reloaded->getInstructions());
    }

    public function testSendInitialMessageFailsForUnknownProject(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        $application = $container->get(\App\Tui\Application::class);
        $projectService = $container->get(\App\Service\ProjectService::class);

        $cmd = new ProjectEditCommand($state, $application, $projectService);
        $cmd->setId(999999);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Project not found.');
        $cmd->sendInitialMessage();
    }

    private function bootAndGetProject(): Project
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixtures should provide demo project');

        return $project;
    }

    private function makeCommand(Project $project): ProjectEditCommand
    {
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        $state->setEditing(true);
        $state->setDynamicIslandComponents([]);
        $state->setProject($project);

        $application = $container->get(\App\Tui\Application::class);
        $projectService = $container->get(\App\Service\ProjectService::class);

        $cmd = new ProjectEditCommand($state, $application, $projectService);
        $cmd->setId($project->getId());

        return $cmd;
    }
}
