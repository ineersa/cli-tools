<?php

namespace App\Tui;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Events\ModeChangedEvent;
use App\Tui\Component\Component;
use App\Tui\Component\ConstraintAwareComponent;
use App\Tui\Component\ContentItem;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * State should only modify it's own properties and dispatch events if needed
 */
class State
{
    private string $model = ''; // current running model

    private Mode $mode; // current running mode

    private string $input = ''; // input buffer

    private int $charIndex = 0; // logical caret index (ASCII chars)

    private int $scrollTopLine = 0; // first visible *wrapped* line in input box

    private int $stickyCol = 0;          // preferred column for up/down (hard lines)

    private bool $editing = true; // editing, input box is active

    private bool $acOpen = false; // if autocomplete active and open at moment

    private int $contentViewportHeight = 30;

    /**
     * @var array<ContentItem>
     */
    private array $contentItems; // widgets to render in content block, must implement Component and ConstraintAwareComponent

    /**
     * @var array <Component&ConstraintAwareComponent>
     */
    private array $dynamicIslandComponents = [];

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

    public function isAcOpen(): bool
    {
        return $this->acOpen;
    }

    public function setAcOpen(bool $acOpen): static
    {
        $this->acOpen = $acOpen;

        return $this;
    }

    public function getStickyCol(): int
    {
        return $this->stickyCol;
    }

    public function setStickyCol(int $stickyCol): static
    {
        $this->stickyCol = $stickyCol;

        return $this;
    }

    public function getContentItems(): array
    {
        return $this->contentItems;
    }

    public function setContentItems(array $contentItems): void
    {
        $this->contentItems = $contentItems;
    }

    public function pushContentItem(ContentItem $contentWidget): void
    {
        $this->contentItems[] = $contentWidget;
    }

    public function popContentItem(): ContentItem
    {
        return array_pop($this->contentItems);
    }

    /**
     * @return array<Component&ConstraintAwareComponent>
     */
    public function getDynamicIslandComponents(): array
    {
        return $this->dynamicIslandComponents;
    }

    /**
     * @param array<Component&ConstraintAwareComponent> $dynamicIslandComponents
     * @return $this
     */
    public function setDynamicIslandComponents(array $dynamicIslandComponents): static
    {
        $this->dynamicIslandComponents = $dynamicIslandComponents;

        return $this;
    }

    public function getContentViewportHeight(): int
    {
        return $this->contentViewportHeight;
    }

    public function setContentViewportHeight(int $contentViewportHeight): static
    {
        $this->contentViewportHeight = $contentViewportHeight;

        return $this;
    }
}
