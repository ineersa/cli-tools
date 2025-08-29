<?php

declare(strict_types=1);

namespace App\Tui;

use App\Agent\Agent;
use App\Events\QuestionReceivedEvent;
use App\Tui\Command\Runner;
use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\Component;
use App\Tui\Component\ContentItemFactory;
use App\Tui\Component\DynamicIslandComponent;
use App\Tui\Component\HelpStringComponent;
use App\Tui\Component\InputComponent;
use App\Tui\Component\ProblemComponent;
use App\Tui\Component\StatusComponent;
use App\Tui\Component\TextContentComponentItems;
use App\Tui\Component\WindowedContentComponent;
use App\Tui\Exception\FollowupException;
use App\Tui\Exception\ProblemException;
use App\Tui\Exception\UserInterruptException;
use App\Tui\Utility\InputUtilities;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class Application
{

    private readonly Layout $layout;

    public function __construct(
        private readonly Terminal $terminal,
        public readonly State $state,
        public readonly Runner $runner,
        public readonly EventDispatcherInterface $eventDispatcher,
    ) {
        $this->layout = new Layout(
            windowedContentComponent: new WindowedContentComponent($state, $terminal),
            autocompleteComponent: new AutocompleteComponent($state, $terminal),
            statusComponent: new StatusComponent($state),
            helpStringComponent: new HelpStringComponent(),
            dynamicIslandComponent: new DynamicIslandComponent($state),
            inputComponent: new InputComponent($state, $terminal),
        );
        $this->state->pushContentItem(TextContentComponentItems::getLogo());
        $this->state->pushContentItem(TextContentComponentItems::getTips());
    }

    /**
     * @throws UserInterruptException
     */
    public function listenTerminalEvents(): void
    {
        // handle events sent to the terminal
        while ((null !== $event = $this->terminal->events()->next())
            || $this->state->isRequireReDrawing()
        ) {
            if ($this->state->isRequireReDrawing()) {
                $this->state->setRequireReDrawing(false);
                continue;
            }
            if ($event instanceof CharKeyEvent) {
                // Ctrl+C -> exit
                if ('c' === $event->char && KeyModifiers::CONTROL === $event->modifiers) {
                    throw new UserInterruptException();
                }
                // Ctrl+D -> submit
                if ('d' === $event->char && KeyModifiers::CONTROL === $event->modifiers) {
                    if (empty($this->state->getInput())
                        || empty(trim($this->state->getInput()))
                    ) {
                        continue;
                    }
                    if (str_starts_with($this->state->getInput(), '/')) {
                        try {
                            $this->runner->runCommand($this->state->getInput());
                        } catch (ProblemException $problemException) {
                            $this->state->setDynamicIslandComponents([
                                ProblemComponent::NAME => new ProblemComponent($problemException, $this->state),
                            ]);
                            continue;
                        } catch (FollowupException $followupException) {
                            continue;
                        }

                        $this->state->pushContentItem(
                            ContentItemFactory::make(ContentItemFactory::COMMAND_CARD, $this->state->getInput())
                        );
                        $this->layout->inputComponent->clearAll();
                        $this->layout->autocompleteComponent->recomputeAutocomplete(resetCursor: true);
                        continue;
                    }

                    // TODO push to history, dispatch event, process command
                    $input = $this->state->getInput();
                    $this->state->pushContentItem(ContentItemFactory::make(ContentItemFactory::USER_CARD, $this->state->getInput()));

                    $this->layout->inputComponent->clearAll();
                    $this->layout->autocompleteComponent->recomputeAutocomplete(resetCursor: true);
                    $this->eventDispatcher->dispatch(new QuestionReceivedEvent($input));
                    continue;
                }
                // Processing char here because it's used later for input and autocomplete
                if ($this->state->isEditing()) {
                    // Accept multi-char bursts (paste), keep ASCII & newlines
                    $chunk = InputUtilities::sanitizePaste($event->char);
                    if ('' !== $chunk) {
                        InputUtilities::insertText($chunk, $this->state);
                        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
                        InputUtilities::updateStickyFromIndex($this->state);
                    }
                }
            }
            if ($event instanceof CodedKeyEvent) {
            }

            foreach ($this->layout->getComponents() as $component) {
                $component->handle($event);
            }
        }
    }

    public function layout(): Widget
    {
        [$wrapped] =
            InputUtilities::wrapTextAndLocateCaret(
                $this->state->getInput(),
                $this->state->getCharIndex(),
                TerminalUtilities::getTerminalInnerWidth($this->terminal)
            );
        $total = max(1, \count($wrapped));
        $inputHeight = 2 + max(1, min(InputComponent::MAX_VISIBLE_LINES, $total));

        $contentH = WindowedContentComponent::CONTENT_HEIGHT;
        // TODO recalculate on resizes?
        $this->state->setContentViewportHeight($contentH);

        return
            GridWidget::default()
                ->direction(Direction::Vertical)
                ->constraints(
                    ...$this->layout->getConstraints($inputHeight)
                )
                ->widgets(...array_map(
                    function (Component $component) {
                        return $component->build();
                    },
                    $this->layout->getComponents()
                ));
    }
}
