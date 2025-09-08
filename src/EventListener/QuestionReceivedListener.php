<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Agent\Agent;
use App\Events\QuestionReceivedEvent;
use App\Message\QuestionReceivedMessage;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use App\Worker\QuestionHandlerWorker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

final class QuestionReceivedListener
{

    public function __construct(
        private MessageBusInterface $messageBus,
        private Agent $agent,
        private QuestionHandlerWorker $questionHandlerWorker,
    ) {
    }

    #[AsEventListener(event: QuestionReceivedEvent::class)]
    public function onQuestionReceivedEvent(QuestionReceivedEvent $event): void
    {
        if (!$this->agent->getProject()) {
            throw new ProblemException('Please create project first');
        }
        $this->messageBus->dispatch(new QuestionReceivedMessage(
            requestId: $event->requestId,
            question: $event->question,
            projectId: $this->agent->getProject()->getId(),
            chatId: $this->agent->setActiveChat()->getActiveChat()?->getId(),
            mode: $this->agent->getMode(),
        ));
        $this->questionHandlerWorker->start($event->requestId, $event->question);
    }
}
