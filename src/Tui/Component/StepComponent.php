<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\DTO\StepComponentDTO;
use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

class StepComponent implements Component, ConstraintAwareComponent
{
    public const NAME = 'step';

    public function __construct(
        private StepComponentDTO $stepComponentDTO,
        private State $state,
    ) {
    }

    public function build(): Widget
    {
        $lines = [];
        if ($this->stepComponentDTO->hint) {
            $lines[] = Line::fromSpans(Span::styled($this->stepComponentDTO->hint, Style::default()->fg(AnsiColor::DarkGray)));
        }
        $lines[] = Line::fromString($this->stepComponentDTO->question);
        if ($this->stepComponentDTO->progress) {
            $lines[] = Line::fromSpans(Span::styled($this->stepComponentDTO->progress, Style::default()->fg(AnsiColor::LightGreen)));
        }

        return BlockWidget::default()
            ->titles(Title::fromString($this->stepComponentDTO->title))
            ->borders(Borders::ALL)
            ->borderStyle($this->stepComponentDTO->borderStyle)
            ->widget(
                ParagraphWidget::fromLines(...$lines),
            );
    }

    public function handle(Event $event): void
    {
        if (!$this->state->getInteractionSession()) {
            $this->state->setDynamicIslandComponents([]);
        }
        if ($event instanceof CodedKeyEvent && KeyCode::Esc === $event->code) {
            $this->state->getInteractionSession()?->cancel();
            $this->state->setDynamicIslandComponents([]);
        }
    }

    public function constraint(): Constraint
    {
        return Constraint::min(1);
    }
}
