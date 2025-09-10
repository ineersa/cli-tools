<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Entity\Chat;
use App\Entity\Project;
use App\EventListener\QuestionReceivedListener;
use App\Events\QuestionReceivedEvent;
use App\Message\QuestionReceivedMessage;
use App\Tui\Exception\ProblemException;
use App\Worker\QuestionHandlerWorker;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class QuestionReceivedListenerTest extends TestCase
{
    private function makeDummyAgent(?Project $project, ?Chat $chat, Mode $mode, ?int &$setActiveChatCalls = null): Agent
    {
        $setActiveChatCalls = 0;
        return new class($project, $chat, $mode, $setActiveChatCalls) extends Agent {
            public function __construct(
                private ?Project $project,
                private ?Chat $chat,
                private Mode $mode,
                private int &$setActiveChatCalls,
            ) {
                // Do not call parent constructor
            }
            public function getProject(): ?Project { return $this->project; }
            public function setActiveChat(): Agent { $this->setActiveChatCalls++; return $this; }
            public function getActiveChat(): ?Chat { return $this->chat; }
            public function getMode(): Mode { return $this->mode; }
            public function getModel(): string { return 'model-x'; }
            public function getSmallModel(): string { return 'model-s'; }
        };
    }

    private function makeWorker(Agent $agent): QuestionHandlerWorker
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $state = new \App\Tui\State($dispatcher, $agent);
        $logger = $this->createMock(LoggerInterface::class);
        $dummyBus = $this->createMock(MessageBusInterface::class);
        $projectDir = dirname(__DIR__, 2);
        return new QuestionHandlerWorker($projectDir, $logger, $state, $agent, $dummyBus);
    }

    public function testOnQuestionReceivedEventThrowsWhenNoProject(): void
    {
        $messageBus = $this->createMock(MessageBusInterface::class);
        $setCalls = 0;
        $agent = $this->makeDummyAgent(null, null, Mode::Chat, $setCalls);
        $worker = $this->makeWorker($agent);

        $messageBus->expects($this->never())->method('dispatch');

        $listener = new QuestionReceivedListener($messageBus, $agent, $worker);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('Please create project first');

        $listener->onQuestionReceivedEvent(new QuestionReceivedEvent('req-1', 'What is up?'));
    }

}
