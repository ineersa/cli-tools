<?php

declare(strict_types=1);

namespace App\Tui;

use App\Agent\Agent;
use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\Component;
use App\Tui\Component\ContentItem;
use App\Tui\Component\ContentItemFactory;
use App\Tui\Component\DynamicIsland;
use App\Tui\Component\HeaderComponentItems;
use App\Tui\Component\HelpStringComponent;
use App\Tui\Component\InputComponent;
use App\Tui\Component\StatusComponent;
use App\Tui\Component\UserCardComponent;
use App\Tui\Component\WindowedContentComponent;
use App\Tui\Exception\UserInterruptException;
use App\Tui\Utility\InputUtilities;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Term\TerminalInformation\Size;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend as PhpTuiPhpTermBackend;
use PhpTui\Tui\Display\Backend;
use PhpTui\Tui\Display\Display;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Bdf\BdfExtension;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Extension\Core\Widget\TabsWidget;
use PhpTui\Tui\Extension\ImageMagick\ImageMagickExtension;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
final class Application
{
    private function __construct(
        private readonly Terminal $terminal,
        private readonly array    $components,
        public readonly State $state
    ) {
    }

    public static function new(Terminal $terminal, Agent $agent, State $state): self
    {
        $components = [
            WindowedContentComponent::class => new WindowedContentComponent($state, $terminal),
            DynamicIsland::class => new DynamicIsland($state),
            HelpStringComponent::class => new HelpStringComponent(),
            InputComponent::class => new InputComponent($state, $terminal),
            AutocompleteComponent::class => new AutocompleteComponent($state, $terminal),
            StatusComponent::class => new StatusComponent($state)
        ];
        $state->pushContentItem(HeaderComponentItems::getLogo());
        $state->pushContentItem(HeaderComponentItems::getTips());

        return new self(
            $terminal,
            $components,
            $state,
        );
    }

    /**
     * @throws UserInterruptException
     */
    public function listenTerminalEvents(): void
    {
        // handle events sent to the terminal
        while (null !== $event = $this->terminal->events()->next()) {
            if ($event instanceof CharKeyEvent) {
                // Ctrl+C -> exit
                if ($event->char === 'c' && KeyModifiers::CONTROL === $event->modifiers) {
                    throw new UserInterruptException();
                }
                // Ctrl+D -> submit
                if ($event->char === 'd' && KeyModifiers::CONTROL === $event->modifiers) {
                    if (empty($this->state->getInput())) {
                        continue;
                    }
                    if (str_starts_with($this->state->getInput(), '/')) {
                        // resolve command
                        $this->state->pushContentItem(ContentItemFactory::make(ContentItemFactory::COMMAND_CARD, $this->state->getInput()));
                        continue;
                    }

                    // TODO push to history, dispatch event, process command
                    $this->state->pushContentItem(ContentItemFactory::make(ContentItemFactory::USER_CARD, $this->state->getInput()));
                    $this->state->pushContentItem(ContentItemFactory::make(ContentItemFactory::RESPONSE_CARD, $this->state->getInput()));

                    $this->components[InputComponent::class]->clearAll();
                    $this->components[AutocompleteComponent::class]->recomputeAutocomplete(resetCursor:true);
                    continue;
                }
            }
            if ($event instanceof CodedKeyEvent) {

            }
            foreach ($this->components as $component) {
                $component->handle($event);
            }
        }
    }

    public function layout(): Widget
    {
        [$wrapped,,] =
            InputUtilities::wrapTextAndLocateCaret(
                $this->state->getInput(),
                $this->state->getCharIndex(),
                TerminalUtilities::getTerminalInnerWidth($this->terminal)
            );
        $total = max(1, count($wrapped));
        $inputHeight = max(1, min(InputComponent::MAX_VISIBLE_LINES, $total));
        // TODO we should catch resize and recalculate it
        $contentH = 30;
        $termH = max(5, $this->terminal->info(Size::class)->lines ?? $contentH);
        $dynIslandH = 10;
        $helpH = 1;
        $inputH = 2 + $inputHeight;
        $acH = AutocompleteComponent::MAX_ROWS_VISIBLE + 1;
        $statusH = 1;

        $contentViewport = max(1, $termH - ($dynIslandH + $helpH + $inputH + $acH + $statusH));
        $this->state->setContentViewportHeight($contentViewport);
        return
            GridWidget::default()
                ->direction(Direction::Vertical)
                ->constraints(
                    Constraint::length($contentH),
                    Constraint::length($dynIslandH),
                    Constraint::length($helpH),
                    Constraint::length($inputH),
                    Constraint::length($acH),
                    Constraint::length($statusH)
                )
                ->widgets(...array_map(
                    function (Component $component) {
                        return $component->build();
                    },
                    $this->components
                ));
    }
}
