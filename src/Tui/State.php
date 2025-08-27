<?php

namespace App\Tui;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Events\ModeChangedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * State should only modify it's own properties and dispatch events if needed
 */
class State
{
    private $model = ''; // current running model

    private Mode $mode; // current running mode

    private $input = ''; // input buffer

    private $charIndex = 0; // logical caret index (ASCII chars)

    private $scrollTopLine = 0; // first visible *wrapped* line in input box

    private $editing = true; // editing, input box is active

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Agent $agent,
    ) {
        $this->mode = Mode::getDefaultMode();
        $this->model = $this->agent->getModel();
    }

    public function getMode(): Mode
    {
        return $this->mode;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setMode(Mode $mode): static
    {
        $this->mode = $mode;
        $this->eventDispatcher->dispatch(new ModeChangedEvent($mode));

        return $this;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function setInput(string $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function appendInput(string $append): static
    {
        $this->input .= $append;

        return $this;
    }

    public function getCharIndex(): int
    {
        return $this->charIndex;
    }

    public function setCharIndex(int $charIndex): static
    {
        $this->charIndex = $charIndex;

        return $this;
    }

    public function getScrollTopLine(): int
    {
        return $this->scrollTopLine;
    }

    public function setScrollTopLine(int $scrollTopLine): static
    {
        $this->scrollTopLine = $scrollTopLine;

        return $this;
    }

    public function isEditing(): bool
    {
        return $this->editing;
    }

    public function setEditing(bool $editing): static
    {
        $this->editing = $editing;

        return $this;
    }


}
