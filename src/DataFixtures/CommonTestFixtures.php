<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Agent\Mode;
use App\Entity\Chat;
use App\Entity\Project;
use App\Service\Chat\ChatStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Common data set for test environment. Loaded via doctrine:fixtures:load --env=test.
 */
final class CommonTestFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // If data already present, keep idempotent by checking existing project by unique name
        $repo = $manager->getRepository(Project::class);
        $existing = $repo->findOneBy(['name' => 'demo']);
        if ($existing instanceof Project) {
            return; // assume already loaded for the test environment
        }

        $project = (new Project())
            ->setName('demo')
            ->setWorkdir(sys_get_temp_dir())
            ->setIsDefault(true)
            ->setInstructions('instructions.md');
        $manager->persist($project);

        $chat1 = (new Chat())
            ->setTitle('First chat')
            ->setMode(Mode::Chat)
            ->setStatus(ChatStatus::Open)
            ->setProject($project);
        $manager->persist($chat1);

        $chat2 = (new Chat())
            ->setTitle('Second chat')
            ->setMode(Mode::Plan)
            ->setStatus(ChatStatus::Archived)
            ->setProject($project);
        $manager->persist($chat2);

        $manager->flush();
    }
}
