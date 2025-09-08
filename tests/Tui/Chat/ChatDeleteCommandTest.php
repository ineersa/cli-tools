<?php

declare(strict_types=1);

namespace App\Tests\Tui\Chat;

use App\Entity\Chat;
use App\Entity\Project;
use App\Service\ChatService;
use App\Tui\Command\Chat\ChatDeleteCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChatDeleteCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ChatService $chatService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->chatService = $container->get(ChatService::class);
    }

    public function testDeleteNotFoundThrowsProblem(): void
    {
        $command = new ChatDeleteCommand($this->chatService);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Chat not found');
        $command->delete(999999);
    }

    public function testDeleteSuccessThrowsCompleteAndRemovesEntity(): void
    {
        $project = $this->getDemoProject();
        $chatId = $this->getAnyChatIdForProject($project);

        $command = new ChatDeleteCommand($this->chatService);

        try {
            $command->delete($chatId);
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/chat delete', $e->getMessage());
            $this->assertStringContainsString("#$chatId", $e->getMessage());
        }

        $this->assertNull($this->em->getRepository(Chat::class)->find($chatId));
    }

    private function getDemoProject(): Project
    {
        $project = $this->em->getRepository(Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixtures should provide a demo project');

        return $project;
    }

    private function getAnyChatIdForProject(Project $project): int
    {
        $chat = $this->em->getRepository(Chat::class)->findOneBy(['project' => $project]);
        $this->assertNotNull($chat, 'Fixtures should provide a chat for demo project');

        return (int) $chat->getId();
    }
}
