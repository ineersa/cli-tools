<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\Exception\ProblemException;
use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

class ProblemComponent implements Component, ConstraintAwareComponent
{
    public const NAME = 'problem';

    public function __construct(
        private ProblemException $problemException,
        private State $state,
    ) {
    }

    public function build(): Widget
    {
        return BlockWidget::default()
            ->titles(Title::fromString('Problem occurred'))
            ->borders(Borders::ALL)
            ->borderStyle(Style::default()->fg(AnsiColor::Red))
            ->widget(
                ParagraphWidget::fromString($this->problemException->getMessage()),
            );
    }

    public function handle(Event $event): void
    {
        $components = $this->state->getDynamicIslandComponents();
        unset($components[self::NAME]);
        $this->state->setDynamicIslandComponents($components);
    }

    public function constraint(): Constraint
    {
        return Constraint::min(1);
    }
}
