<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\Agent\Mode;
use App\Entity\Project;
use App\Message\AssistantResponseReceivedMessage;
use App\Repository\ChatRepository;
use App\Repository\ChatTurnRepository;
use App\Repository\ProjectRepository;
use App\Service\Chat\ChatStatus;
use App\Service\Chat\ChatTurnType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class AssistantResponseReceivedMessageHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private MessageBusInterface $bus;
    private ProjectRepository $projectRepository;
    private ChatRepository $chatRepository;
    private ChatTurnRepository $chatTurnRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->bus = $container->get(MessageBusInterface::class);
        $this->projectRepository = $container->get(ProjectRepository::class);
        $this->chatRepository = $container->get(ChatRepository::class);
        $this->chatTurnRepository = $container->get(ChatTurnRepository::class);
    }

    public function testHandlesMessageForExistingChatAndCreatesAssistantTurn(): void
    {
        // Arrange
        $project = $this->getDemoProject();
        $chat = $this->chatRepository->findOneBy([
            'project' => $project->getId(),
            'mode' => Mode::Chat,
            'status' => ChatStatus::Open,
        ]);
        self::assertNotNull($chat, 'Expected an open Chat from fixtures.');

        $requestId = 'resp-existing-'.uniqid('', true);
        $response = 'Hello! I am fine, thanks for asking.';
        $finishReason = 'stop';
        $promptTokens = 10;
        $completionTokens = 5;
        $totalTokens = 15;

        // Act: dispatch and process
        $this->bus->dispatch(new AssistantResponseReceivedMessage(
            projectId: $project->getId(),
            requestId: $requestId,
            response: $response,
            mode: Mode::Chat,
            chatId: $chat->getId(),
            finishReason: $finishReason,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
        ));
        $this->transport('async')->process(1);

        // Assert: assistant turn stored
        $turn = $this->chatTurnRepository->findOneBy(['request_id' => $requestId]);
        self::assertNotNull($turn, 'Expected assistant ChatTurn to be created.');
        self::assertSame(ChatTurnType::Assistant, $turn->getType());
        self::assertSame($chat->getId(), $turn->getChat()?->getId());
        self::assertSame($response, $turn->getContext());
        self::assertSame($finishReason, $turn->getFinishReason());
        self::assertSame($promptTokens, $turn->getPromptTokens());
        self::assertSame($completionTokens, $turn->getCompletionTokens());
        self::assertSame($totalTokens, $turn->getTotalTokens());

        $chatRefreshed = $this->chatRepository->find($chat->getId());
        self::assertNotNull($chatRefreshed?->getLastTurn());
        self::assertSame($requestId, $chatRefreshed?->getLastTurn()?->getRequestId());
    }

    public function testCreatesAssistantTurnResolvingOpenChatWhenChatIdNotProvided(): void
    {
        // Arrange
        $project = $this->getDemoProject();
        $requestId = 'resp-new-'.uniqid('', true);
        $response = 'Using the open chat automatically.';

        // Act
        $this->bus->dispatch(new AssistantResponseReceivedMessage(
            projectId: $project->getId(),
            requestId: $requestId,
            response: $response,
            mode: Mode::Chat,
            chatId: null,
        ));
        $this->transport('async')->process(1);

        // Assert
        $turn = $this->chatTurnRepository->findOneBy(['request_id' => $requestId]);
        self::assertNotNull($turn, 'Expected assistant ChatTurn to be created.');
        self::assertSame(ChatTurnType::Assistant, $turn->getType());
        self::assertSame($response, $turn->getContext());

        $chat = $turn->getChat();
        self::assertNotNull($chat, 'Turn should be linked to a chat.');
        self::assertSame($project->getId(), $chat->getProject()?->getId());
        self::assertSame(Mode::Chat, $chat->getMode());
        self::assertSame(ChatStatus::Open, $chat->getStatus());

        self::assertSame($turn->getId(), $chat->getLastTurn()?->getId());
    }

    private function getDemoProject(): Project
    {
        $project = $this->projectRepository->findOneBy(['name' => 'demo']);
        self::assertInstanceOf(Project::class, $project, 'Expected demo project from CommonTestFixtures.');

        return $project;
    }
}
