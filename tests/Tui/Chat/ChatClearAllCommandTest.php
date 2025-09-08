<?php

declare(strict_types=1);

namespace App\Tests\Tui\Chat;

use App\Entity\Chat;
use App\Entity\Project;
use App\Service\ChatService;
use App\Tui\Command\Chat\ChatClearAllCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChatClearAllCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ChatService $chatService;
    private State $state;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->chatService = $container->get(ChatService::class);
        $this->state = $container->get(State::class);
        $this->state->setDynamicIslandComponents([]);
        $this->state->setEditing(true);
    }

    public function testClearAllDeletesAllForProjectAndCompletesWithCount(): void
    {
        $project = $this->getDemoProject();
        $this->state->setProject($project);

        $c1 = (new Chat())
            ->setProject($project)
            ->setTitle('c1')
            ->setMode(\App\Agent\Mode::Chat)
            ->setStatus(\App\Service\Chat\ChatStatus::Open);
        $c2 = (new Chat())
            ->setProject($project)
            ->setTitle('c2')
            ->setMode(\App\Agent\Mode::Chat)
            ->setStatus(\App\Service\Chat\ChatStatus::Open);
        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->flush();

        $before = (int) $this->em->getRepository(Chat::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.project = :p')
            ->setParameter('p', $project)
            ->getQuery()->getSingleScalarResult();

        $command = new ChatClearAllCommand($this->chatService, $this->state);

        try {
            $command->clearAll();
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/chat clear-all', $e->getMessage());
            $this->assertStringContainsString((string) $before, $e->getMessage());
        }

        $after = (int) $this->em->getRepository(Chat::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.project = :p')
            ->setParameter('p', $project)
            ->getQuery()->getSingleScalarResult();
        $this->assertSame(0, $after);
    }

    private function getDemoProject(): Project
    {
        $project = $this->em->getRepository(Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixtures should provide a demo project');

        return $project;
    }
}
