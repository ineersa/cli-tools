<?php

declare(strict_types=1);

namespace App\Tests\Tui\Chat;

use App\Entity\Chat;
use App\Entity\Project;
use App\Service\ChatService;
use App\Tui\Command\Chat\ChatSummaryCommand;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChatSummaryCommandTest extends KernelTestCase
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

    public function testSummaryNotFoundThrowsProblem(): void
    {
        $command = new ChatSummaryCommand($this->chatService);
        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Chat not found');
        $command->summary(999999);
    }

    public function testSummaryNoSummaryCompletesWithMessage(): void
    {
        $project = $this->getDemoProject();
        $chat = new Chat();
        $chat->setProject($project)
            ->setTitle('no summary')
            ->setMode(\App\Agent\Mode::Chat)
            ->setStatus(\App\Service\Chat\ChatStatus::Open);
        $this->em->persist($chat);
        $this->em->flush();
        $chatId = (int) $chat->getId();

        $command = new ChatSummaryCommand($this->chatService);

        try {
            $command->summary($chatId);
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/chat summary', $e->getMessage());
            $this->assertStringContainsString('No summary yet', $e->getMessage());
        }
    }

    public function testSummaryWithSummaryCompletesWithSummaryText(): void
    {
        $project = $this->getDemoProject();
        $chat = new Chat();
        $chat->setProject($project)
            ->setTitle('has summary')
            ->setMode(\App\Agent\Mode::Chat)
            ->setStatus(\App\Service\Chat\ChatStatus::Open)
            ->setSummary('Summary text here');
        $this->em->persist($chat);
        $this->em->flush();
        $chatId = (int) $chat->getId();

        $command = new ChatSummaryCommand($this->chatService);

        try {
            $command->summary($chatId);
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/chat summary', $e->getMessage());
            $this->assertStringContainsString('Summary:', $e->getMessage());
            $this->assertStringContainsString('Summary text here', $e->getMessage());
        }
    }

    private function getDemoProject(): Project
    {
        $project = $this->em->getRepository(Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Fixtures should provide a demo project');

        return $project;
    }
}
