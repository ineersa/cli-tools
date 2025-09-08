<?php

declare(strict_types=1);

namespace App\Tests\Tui\Project;

use App\Service\ProjectService;
use App\Tui\Command\Project\ProjectDeleteCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectDeleteCommandTest extends KernelTestCase
{
    public function testDeleteExistingProjectCompletesAndRemovesEntity(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        // Ensure fixture project exists
        $projectRepo = $em->getRepository(\App\Entity\Project::class);
        $project = $projectRepo->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixture project "demo" should exist');

        // Create a secondary project to allow default reassignment logic in service
        $secondary = (new \App\Entity\Project())
            ->setName('secondary')
            ->setWorkdir(sys_get_temp_dir())
            ->setIsDefault(false)
            ->setInstructions('instructions.md');
        $em->persist($secondary);
        $em->flush();

        // Make sure the demo project is default to exercise delete default branch
        $project->setIsDefault(true);
        $em->flush();

        /** @var ProjectService $projectService */
        $projectService = $container->get(ProjectService::class);
        $command = new ProjectDeleteCommand($projectService);

        $id = (int) $project->getId();
        try {
            $command->delete($id);
        } catch (CompleteException $exception) {
            $this->assertStringContainsString('/project delete', $exception->getMessage());
            $this->assertStringContainsString('was successfully deleted', $exception->getMessage());
        }

        // Verify the entity is removed
        $deleted = $projectRepo->find($id);
        $this->assertNull($deleted, 'Project should be deleted');

        // Verify that another project became default after deletion
        $newDefault = $projectRepo->findOneBy(['is_default' => true]);
        $this->assertNotNull($newDefault, 'Another project should be set as default after deleting the default project');
        $this->assertSame($secondary->getId(), $newDefault->getId(), 'Secondary project should become default');
    }

    public function testDeleteNonExistingProjectThrowsProblemException(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var ProjectService $projectService */
        $projectService = $container->get(ProjectService::class);
        $command = new ProjectDeleteCommand($projectService);

        $nonExistingId = 999999; // unlikely to exist in test DB

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Project not found');
        $command->delete($nonExistingId);
    }
}
