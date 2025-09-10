<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\Agent\Mode;
use App\Entity\Project;
use App\Message\QuestionReceivedMessage;
use App\Repository\ChatRepository;
use App\Repository\ChatTurnRepository;
use App\Repository\ProjectRepository;
use App\Service\Chat\ChatStatus;
use App\Service\Chat\ChatTurnType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class QuestionReceivedMessageHandlerTest extends KernelTestCase
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

    public function testHandlesMessageForExistingChatAndCreatesUserTurn(): void
    {
        // Arrange: grab demo project and its open chat from fixtures
        $project = $this->getDemoProject();
        $chat = $this->chatRepository->findOneBy([
            'project' => $project->getId(),
            'mode' => Mode::Chat,
            'status' => ChatStatus::Open,
        ]);
        self::assertNotNull($chat, 'Expected an open Chat from fixtures.');

        $requestId = 'req-existing-'.uniqid('', true);
        $question = 'How are you today?';

        // Act: dispatch message to async transport and process it
        $this->bus->dispatch(new QuestionReceivedMessage(
            requestId: $requestId,
            question: $question,
            projectId: $project->getId(),
            chatId: $chat->getId(),
            mode: Mode::Chat,
        ));

        // process 1 message from async transport (handled by QuestionReceivedMessageHandler)
        $this->transport('async')->process(1);

        // Assert: a new user turn exists in DB with our request id, linked to the same chat
        $turn = $this->chatTurnRepository->findOneBy(['request_id' => $requestId]);
        self::assertNotNull($turn, 'Expected ChatTurn to be created.');
        self::assertSame(ChatTurnType::User, $turn->getType());
        self::assertSame($chat->getId(), $turn->getChat()?->getId());
        self::assertSame($question, $turn->getContext());

        // Also ensure the chat's last turn updated to this new turn
        $chatRefreshed = $this->chatRepository->find($chat->getId());
        self::assertNotNull($chatRefreshed?->getLastTurn());
        self::assertSame($requestId, $chatRefreshed?->getLastTurn()?->getRequestId());
    }

    public function testCreatesNewChatWhenNoneProvidedAndPersistsUserTurn(): void
    {
        // Arrange: use the demo project, do not provide chatId
        $project = $this->getDemoProject();
        $requestId = 'req-new-'.uniqid('', true);
        $question = 'Create new chat please with this long title that will be trimmed';

        // Act
        $this->bus->dispatch(new QuestionReceivedMessage(
            requestId: $requestId,
            question: $question,
            projectId: $project->getId(),
            chatId: null,
            mode: Mode::Chat,
        ));
        $this->transport('async')->process(1);

        // Assert: find the created turn by request id
        $turn = $this->chatTurnRepository->findOneBy(['request_id' => $requestId]);
        self::assertNotNull($turn, 'Expected ChatTurn to be created for new chat.');
        self::assertSame(ChatTurnType::User, $turn->getType());
        self::assertSame($question, $turn->getContext());

        $chat = $turn->getChat();
        self::assertNotNull($chat, 'Expected Chat to be created.');
        self::assertSame($project->getId(), $chat->getProject()?->getId());
        self::assertSame(Mode::Chat, $chat->getMode());
        self::assertSame(ChatStatus::Open, $chat->getStatus());

        // Title is set to first up to 30 chars of question
        $expectedTitle = substr($question, 0, min(30, \strlen($question)));
        self::assertSame($expectedTitle, $chat->getTitle());

        // chat last turn is the created turn
        self::assertSame($turn->getId(), $chat->getLastTurn()?->getId());
    }

    private function getDemoProject(): Project
    {
        $project = $this->projectRepository->findOneBy(['name' => 'demo']);
        self::assertInstanceOf(Project::class, $project, 'Expected demo project from CommonTestFixtures.');

        return $project;
    }
}
