<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

class ProjectService
{
    public readonly ProjectRepository $projectRepository;
    private ?ObjectManager $manager;

    public function __construct(
        ManagerRegistry $managerRegistry,
    ) {
        $this->projectRepository = $managerRegistry->getRepository(Project::class);
        $this->manager = $managerRegistry->getManagerForClass(Project::class);
    }

    public function getDefaultProject(): ?Project
    {
        return $this->projectRepository
            ->findOneBy(['is_default' => true]);
    }

    /**
     * @param array{name: string, workdir: string, is_default: bool, instructions: string} $data
     */
    public function create(array $data): Project
    {
        $entity = new Project();
        $entity->setName($data['name']);
        $entity->setWorkdir($data['workdir']);
        $entity->setIsDefault($data['is_default']);
        $entity->setInstructions($data['instructions']);

        if ($entity->isDefault()) {
            foreach ($this->projectRepository->findAll() as $project) {
                $project->setIsDefault(false);
            }
        }

        $this->manager->persist($entity);
        $this->manager->flush();

        return $entity;
    }

    public function delete(Project $project): void
    {
        if ($project->isDefault()) {
            $first = $this->projectRepository->findOneBy([], ['id' => 'ASC']);
            $first->setIsDefault(true);
        }
        $this->manager->remove($project);
        $this->manager->flush();
    }

    public function update(Project $project): void
    {
        if ($project->isDefault()) {
            foreach ($this->projectRepository->findAll() as $project) {
                $project->setIsDefault(false);
            }
        }

        $this->manager->flush();
    }
}
