<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class DynamicIslandComponent implements Component
{
    public const MIN_HEIGHT = 5;

    public function __construct(
        private State $state,
    ) {
    }

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
