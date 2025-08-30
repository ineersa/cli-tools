<?php

namespace App\EventListener;

use App\Agent\Agent;
use App\Events\QuestionReceivedEvent;
use App\Tui\Component\ContentItemFactory;
use App\Tui\State;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class QuestionReceivedListener
{
    public function __construct(
        private Agent $agent,
        private State $state,
    ) {

    }

    #[AsEventListener(event: QuestionReceivedEvent::class)]
    public function onQuestionReceivedEvent($event): void
    {
//        $response = $this->agent->getChat()->generateText($event->question);
//        $this->state->pushContentItem(ContentItemFactory::make(ContentItemFactory::RESPONSE_CARD, $response));
    }
}
