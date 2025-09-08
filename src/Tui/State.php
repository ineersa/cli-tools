<?php

declare(strict_types=1);

namespace App\Tui;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Entity\Project;
use App\Events\ModeChangedEvent;
use App\Tui\Command\InteractionSessionInterface;
use App\Tui\Component\Component;
use App\Tui\Component\ConstraintAwareComponent;
use App\Tui\Component\ContentItem;
use App\Tui\Component\WindowedContentComponent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * State should only modify it's own properties and dispatch events if needed.
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

    private int $contentViewportHeight = WindowedContentComponent::CONTENT_HEIGHT;

    /**
     * @var array<ContentItem>
     */
    private array $contentItems; // widgets to render in content block, must implement Component and ConstraintAwareComponent

    /**
     * @var array <Component&ConstraintAwareComponent>
     */
    private array $dynamicIslandComponents = [];

    private bool $requireReDrawing = false;

    private ?InteractionSessionInterface $interactionSession = null;
    private Project $project;
    private string $smallModel;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Agent $agent,
    ) {
        $this->mode = Mode::getDefaultMode();
        $this->model = $this->agent->getModel();
        $this->smallModel = $this->agent->getSmallModel();
        $this->project = $this->agent->getProject();
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

    /**
     * @return ContentItem[]
     */
    public function getContentItems(): array
    {
        return $this->contentItems;
    }

    /**
     * @param ContentItem[] $contentItems
     */
    public function setContentItems(array $contentItems): void
    {
        $this->contentItems = $contentItems;
    }

    public function pushContentItem(ContentItem $contentWidget, ?int $index = null): int
    {
        if ($index === null) {
            $this->contentItems[] = $contentWidget;
            return array_key_last($this->contentItems);
        }

        $count = count($this->contentItems);
        if ($index < 0) {
            $index = 0;
        }

        if ($index < $count) {
            array_splice($this->contentItems, $index, 1, [$contentWidget]);
            return $index;
        }

        $this->contentItems[] = $contentWidget;
        return array_key_last($this->contentItems);
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
     *
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

    public function isRequireReDrawing(): bool
    {
        return $this->requireReDrawing;
    }

    public function setRequireReDrawing(bool $requireReDrawing): static
    {
        $this->requireReDrawing = $requireReDrawing;

        return $this;
    }

    public function getInteractionSession(): ?InteractionSessionInterface
    {
        return $this->interactionSession;
    }

    public function setInteractionSession(?InteractionSessionInterface $interactionSession): static
    {
        $this->interactionSession = $interactionSession;

        return $this;
    }

    public function getProject(): Project|null
    {
        return $this->project;
    }

    public function setProject(Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getSmallModel(): string
    {
        return $this->smallModel;
    }

    public function setSmallModel(string $smallModel): self
    {
        $this->smallModel = $smallModel;

        return $this;
    }
}
