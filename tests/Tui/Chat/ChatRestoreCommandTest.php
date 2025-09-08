<?php

declare(strict_types=1);

namespace App\Tests\Tui\Chat;

use App\Entity\Chat;
use App\Entity\Project;
use App\Service\ChatService;
use App\Tui\Command\Chat\ChatRestoreCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChatRestoreCommandTest extends KernelTestCase
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

    public function testRestoreNotFoundThrowsProblem(): void
    {
        $this->state->setProject($this->getDemoProject());
        $command = new ChatRestoreCommand($this->chatService, $this->state);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Chat not found');
        $command->restore(999999);
    }

    public function testRestoreSuccessThrowsCompleteAndOpensChat(): void
    {
        $project = $this->getDemoProject();
        $this->state->setProject($project);

        $chat = new Chat();
        $chat->setProject($project)
            ->setTitle('to restore')
            ->setMode(\App\Agent\Mode::Chat)
            ->setStatus(\App\Service\Chat\ChatStatus::Archived);
        $this->em->persist($chat);
        $this->em->flush();
        $chatId = (int) $chat->getId();

        $command = new ChatRestoreCommand($this->chatService, $this->state);

        try {
            $command->restore($chatId);
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/chat restore', $e->getMessage());
            $this->assertStringContainsString("#$chatId", $e->getMessage());
        }

        $refreshed = $this->em->getRepository(Chat::class)->find($chatId);
        $this->assertNotNull($refreshed);
        $this->assertEquals(\App\Service\Chat\ChatStatus::Open, $refreshed->getStatus());
    }

    private function getDemoProject(): Project
    {
        $project = $this->em->getRepository(Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixtures should provide a demo project');

        return $project;
    }
}
