<?php

declare(strict_types=1);

namespace App\Tui;

use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\DynamicIslandComponent;
use App\Tui\Component\HelpStringComponent;
use App\Tui\Component\InputComponent;
use App\Tui\Component\StatusComponent;
use App\Tui\Component\WindowedContentComponent;
use PhpTui\Tui\Layout\Constraint;

class Layout
{
    public function __construct(
        public readonly WindowedContentComponent $windowedContentComponent,
        public readonly AutocompleteComponent $autocompleteComponent,
        public readonly StatusComponent $statusComponent,
        public readonly HelpStringComponent $helpStringComponent,
        public readonly DynamicIslandComponent $dynamicIslandComponent,
        public readonly InputComponent $inputComponent,
    ) {
    }

    /**
     * @return array<Component\Component>
     */
    public function getComponents(): array
    {
        return [
            $this->windowedContentComponent,
            $this->autocompleteComponent,
            $this->helpStringComponent,
            $this->inputComponent,
            $this->dynamicIslandComponent,
            $this->statusComponent,
        ];
    }

    /**
     * @return array<Constraint>
     */
    public function getConstraints(int $inputHeight): array
    {
        return [
            Constraint::length(WindowedContentComponent::CONTENT_HEIGHT),
            Constraint::length(AutocompleteComponent::MAX_ROWS_VISIBLE + 1),
            Constraint::length(HelpStringComponent::HEIGHT),
            Constraint::length($inputHeight),
            Constraint::min(DynamicIslandComponent::MIN_HEIGHT),
            Constraint::length(StatusComponent::HEIGHT),
        ];
    }
}
