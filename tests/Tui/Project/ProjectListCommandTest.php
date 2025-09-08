<?php

declare(strict_types=1);

namespace App\Tests\Tui\Project;

use App\Tui\Command\Project\ProjectListCommand;
use App\Tui\Component\TableListComponent;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectListCommandTest extends KernelTestCase
{
    public function testListDisplaysTableAndCompletesWithCountWhenProjectsExist(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var State $state */
        $state = $container->get(State::class);
        $state->setDynamicIslandComponents([]);
        $state->setEditing(true);

        $projectService = $container->get(\App\Service\ProjectService::class);
        $serializer = $container->get(\Symfony\Component\Serializer\SerializerInterface::class);

        // Determine expected project count dynamically to avoid cross-test ordering issues
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $count = \count($em->getRepository(\App\Entity\Project::class)->findAll());

        $command = new ProjectListCommand($projectService, $state, $serializer);

        try {
            $command->list();
        } catch (CompleteException $exception) {
            $this->assertStringContainsString('/projects list', $exception->getMessage());
            $this->assertStringContainsString('Found '.$count.' projects', $exception->getMessage());
        }

        $components = $state->getDynamicIslandComponents();
        $this->assertArrayHasKey(TableListComponent::NAME, $components);
        $this->assertInstanceOf(TableListComponent::class, $components[TableListComponent::NAME]);
        $this->assertFalse($state->isEditing());
    }

    public function testListCompletesWithNoProjectsMessageWhenEmpty(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var State $state */
        $state = $container->get(State::class);

        // Ensure database has no projects: remove all after obtaining State to avoid constructor null project
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        $repo = $em->getRepository(\App\Entity\Project::class);
        foreach ($repo->findAll() as $project) {
            $em->remove($project);
        }
        $em->flush();
        $state->setDynamicIslandComponents([]);
        $state->setEditing(true);

        $projectService = $container->get(\App\Service\ProjectService::class);
        $serializer = $container->get(\Symfony\Component\Serializer\SerializerInterface::class);

        $command = new ProjectListCommand($projectService, $state, $serializer);

        $this->expectException(CompleteException::class);
        $this->expectExceptionMessageMatches('/\\/projects list \\n No projects were found/');
        $command->list();
    }
}
