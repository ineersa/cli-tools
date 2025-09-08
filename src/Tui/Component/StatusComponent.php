<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class StatusComponent implements Component
{
    public const HEIGHT = 1;

    public function __construct(
        private State $state,
    ) {
    }

    public function build(): Widget
    {
        $statusLeft = ParagraphWidget::fromSpans(
            Span::fromString($this->state->getProject()?->getName() ?? ''),
            Span::styled('('.($this->state->getProject()?->getWorkdir() ?? '').')', Style::default()->cyan())
        );
        $statusCenter = ParagraphWidget::fromText(Text::fromString($this->state->getMode()->value.' (Shift+Tab)')->red());
        $statusRight = ParagraphWidget::fromSpans(
            Span::styled($this->state->getModel(), Style::default()->green()),
            Span::styled('('.$this->state->getSmallModel().')', Style::default()->darkGray())
        );

        $statusGrid = GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(45),
                Constraint::percentage(30),
                Constraint::percentage(35),
            )
            ->widgets($statusLeft, $statusCenter, $statusRight);

        return BlockWidget::default()
            ->borders(Borders::NONE)
            ->widget($statusGrid);
    }

    public function handle(Event $event): void
    {
        if ($event instanceof CodedKeyEvent) {
            if (KeyCode::BackTab === $event->code) {
                $this->state->setMode($this->state->getMode()->getNextMode());
            }
        }
    }
}
