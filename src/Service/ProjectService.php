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
        $this->projectRepository = $managerRegistry->getRepository(Project::class); // @phpstan-ignore-line
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
            // Choose another project (by id ASC) that is not the one being deleted
            $candidates = $this->projectRepository->findBy([], ['id' => 'ASC']);
            foreach ($candidates as $candidate) {
                if ($candidate->getId() !== $project->getId()) {
                    $candidate->setIsDefault(true);
                    break;
                }
            }
        }
        $this->manager->remove($project);
        $this->manager->flush();
    }

    public function update(Project $target): void
    {
        if ($target->isDefault()) {
            foreach ($this->projectRepository->findAll() as $other) {
                if ($other->getId() !== $target->getId()) {
                    $other->setIsDefault(false);
                }
            }
            // Ensure the target remains default
            $target->setIsDefault(true);
        }

        $this->manager->flush();
    }
}
