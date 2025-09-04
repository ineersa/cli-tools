<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Agent\Agent;
use App\Events\QuestionReceivedEvent;
use App\Tui\Component\ContentItemFactory;
use App\Tui\State;
use App\Worker\QuestionHandlerWorker;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class QuestionReceivedListener
{
    public function __construct(
        private Agent $agent,
        private State $state,
        private QuestionHandlerWorker $questionHandlerWorker,
    ) {
    }

    #[AsEventListener(event: QuestionReceivedEvent::class)]
    public function onQuestionReceivedEvent(QuestionReceivedEvent $event): void
    {
        $this->questionHandlerWorker->start($event->requestId, $event->question);
    }
}
