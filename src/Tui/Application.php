<?php

declare(strict_types=1);

namespace App\Tui;

use App\Agent\Agent;
use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\Component;
use App\Tui\Component\ContentComponent;
use App\Tui\Component\HelpStringComponent;
use App\Tui\Component\InputComponent;
use App\Tui\Component\StatusComponent;
use App\Tui\Exceptions\UserInterruptException;
use App\Tui\Utilities\InputUtilities;
use App\Tui\Utilities\TerminalUtilities;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
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
            ContentComponent::class => new ContentComponent($state),
            HelpStringComponent::class => new HelpStringComponent(),
            InputComponent::class => new InputComponent($state, $terminal),
            AutocompleteComponent::class => new AutocompleteComponent($state),
            StatusComponent::class => new StatusComponent($state)
        ];

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
                    // TODO push to history, dispatch event
                    $this->components[InputComponent::class]->clearAll();
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

        return
            GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(
                Constraint::max(25),
                Constraint::length(1),
                Constraint::length(2 + $inputHeight),
                Constraint::length(9),
                Constraint::length(1)
            )
            ->widgets(...array_map(
                function (Component $component) {
                    return $component->build();
                },
                $this->components
            ));
    }
}
