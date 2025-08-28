<?php

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\CompositeWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class DynamicIsland implements Component
{

    public function __construct(
        private State $state,
    ) {}

    public function build(): Widget
    {
        $components = $this->state
            ->getDynamicIslandComponents();
        return GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(
                ...array_map(function (ConstraintAwareComponent $component) {
                    return $component->constraint();
                }, $components)
            )
            ->widgets(
                ...array_map(function (ConstraintAwareComponent $component) {
                    return $component->build();
                }, $components)
            );
    }

    public function handle(Event $event): void
    {
        foreach ($this->state->getDynamicIslandComponents() as $component) {
            $component->handle($event);
        }
    }
}
