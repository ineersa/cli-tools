<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\Agent\Mode;
use App\Entity\Project;
use App\Llm\LlmClient;
use App\Message\CreateSummaryMessage;
use App\Repository\ChatRepository;
use App\Repository\ProjectRepository;
use OpenAI\Responses\Chat\CreateResponse as ChatCreateResponse;
use OpenAI\Testing\ClientFake;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class CreateSummaryMessageHandlerTest extends KernelTestCase
{
    use InteractsWithMessenger;

    private MessageBusInterface $bus;
    private ProjectRepository $projectRepository;
    private ChatRepository $chatRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->bus = $container->get(MessageBusInterface::class);
        $this->projectRepository = $container->get(ProjectRepository::class);
        $this->chatRepository = $container->get(ChatRepository::class);
    }

    public function testSummaryIsCreatedAndPersistedForExistingChat(): void
    {
        // Arrange: get demo project and its open chat from fixtures
        $project = $this->getDemoProject();
        $chat = $this->chatRepository->findOneBy([
            'project' => $project->getId(),
            'mode' => Mode::Chat,
            'status' => 'open', // status is string enum in DB column
        ]);
        self::assertNotNull($chat, 'Expected an open Chat from fixtures.');

        $expectedSummary = 'This is a concise summary produced by the model.';

        // Fake OpenAI chat completion to return our expected summary
        $clientFake = new ClientFake([
            ChatCreateResponse::fake([
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $expectedSummary,
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
            ]),
        ]);

        // Inject fake client into existing llm.smallModel service
        /** @var LlmClient $smallModel */
        $smallModel = self::getContainer()->get('llm.smallModel');
        $smallModel->setOpenAIClient($clientFake);

        // Act: dispatch the message and process the 'summary' transport
        $this->bus->dispatch(new CreateSummaryMessage($chat->getId()));
        $this->transport('summary')->process(1);

        // Assert: chat summary updated in DB
        $refreshed = $this->chatRepository->find($chat->getId());
        self::assertNotNull($refreshed, 'Chat must exist.');
        self::assertSame($expectedSummary, $refreshed->getSummary());
    }

    private function getDemoProject(): Project
    {
        $project = $this->projectRepository->findOneBy(['name' => 'demo']);
        self::assertInstanceOf(Project::class, $project, 'Expected demo project from CommonTestFixtures.');

        return $project;
    }
}
