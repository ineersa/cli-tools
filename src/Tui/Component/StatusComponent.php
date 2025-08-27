<?php

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class StatusComponent implements Component
{
    private string $cwd;
    public function __construct(
        private State $state,
    )
    {
        $this->cwd = getcwd() ?: '~';
    }

    public function build(): Widget
    {
        $statusLeft = ParagraphWidget::fromText(Text::fromString($this->cwd)->cyan());
        $statusCenter = ParagraphWidget::fromText(Text::fromString($this->state->getMode()->value . ' (Shift+Tab)')->red());
        $statusRight = ParagraphWidget::fromText(Text::fromString($this->state->getModel() . ' 100%')->green());

        $statusGrid = GridWidget::default()
            ->direction(Direction::Horizontal)
            ->constraints(
                Constraint::percentage(45),
                Constraint::percentage(45),
                Constraint::percentage(10),
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
