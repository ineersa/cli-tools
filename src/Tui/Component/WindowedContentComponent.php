<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\CompositeWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;
use SebastianBergmann\LinesOfCode\LinesOfCode;

/**
 * A scrollable container that shows only the visible tail of a growing list of "cards".
 * Each card is measured (wrapped) to compute height in lines for the current width.
 */
final class WindowedContentComponent implements Component
{
    /** @var list<array{t:Text,h:int}> */
    private array $cards = [];

    /** top-of-viewport line offset from the very start (0 = top) */
    private int $scrollY = 0;

    /** true if user scrolled up; new cards won’t auto-stick to bottom */
    private bool $pinnedUp = false;

    /** chrome lines inside our block (borders/titles/padding) */
    private int $chrome = 2; // top + bottom border;

    public function __construct(private State $state, private Terminal $terminal) {}

    public function build(): Widget
    {
        $viewportH = max(1, $this->state->getContentViewportHeight() - $this->chrome);
        $viewportW = max(10, TerminalUtilities::getTerminalInnerWidth($this->terminal));

        // 1) Measure any cards with unknown height for this width
        foreach ($this->state->getContentItems() as $contentItem) {
            if ($contentItem->height === 0) {
                $contentItem->height = $this->measureTextHeight($contentItem->text, $viewportW);
            }
        }
        // 2) If not pinned up, keep scrollY at bottom
        $total = $this->totalHeight();
        if (!$this->pinnedUp) {
            $this->scrollY = max(0, $total - $viewportH);
        } else {
            // clamp if content shrank / resized
            $this->scrollY = min($this->scrollY, max(0, $total - 1));
        }
        // 3) Compute which slice fits in viewport (from scrollY)
        [$startIdx, $skipLines] = $this->locateStartCard($this->scrollY);
        $visible = $this->collectVisible($startIdx, $skipLines, $viewportH);

        // 4) Render visible cards as a vertical grid of Paragraphs
        $constraints = [];
        $widgets     = [];

        foreach ($visible as $item) {
            $constraints[] = Constraint::length($item['height']);
            if ($item['item']->prefixSpan && $item['item']->originalString) {
                $firstLine = $item['item']->text->lines[0];
                $otherLines = array_slice($item['item']->text->lines, 1);
                $spanLine = Line::fromSpans($item['item']->prefixSpan, ...$firstLine->spans);

                $widgets[] = ParagraphWidget::fromLines($spanLine, ...$otherLines);
            } else {
                $widgets[] = ParagraphWidget::fromText($item['item']->text)->style($item['item']->style);
            }
        }

        // If you want a framed “Conversation” block:
        $inner = GridWidget::default()
            ->direction(Direction::Vertical)
            ->constraints(...$constraints)
            ->widgets(...$widgets);

        return CompositeWidget::fromWidgets(
            BlockWidget::default()
                ->borders(Borders::NONE)
                ->titles()
                ->padding(Padding::horizontal(1)),
            $inner
        );
    }

    public function handle(Event $event): void
    {
        if (!$event instanceof CodedKeyEvent) {
            return;
        }

        $page = max(1, $this->state->getContentViewportHeight() - $this->chrome - 1);
        $maxTop = max(0, $this->totalHeight() - max(1, $this->state->getContentViewportHeight() - $this->chrome));

        switch ($event->code) {
            case KeyCode::PageUp:
                $this->scrollY = max(0, $this->scrollY - $page);
                $this->pinnedUp = ($this->scrollY < $maxTop);
                break;
            case KeyCode::PageDown:
                $this->scrollY = min($maxTop, $this->scrollY + $page);
                $this->pinnedUp = ($this->scrollY < $maxTop);
                break;
        }
    }

    // ---- helpers ----

    private function measureTextHeight(Text $t, int $width): int
    {
        // naive wrapping: count "soft" line wraps by splitting spans to plain strings.
        $lines = 0;
        foreach ($t->lines as $line) {
            $s = '';
            foreach ($line->spans as $span) {
                $s .= $span->content;
            }
            $lines += max(1, (int)ceil(mb_strlen($s) / max(1, $width)));
        }
        return $lines;
    }

    private function totalHeight(): int
    {
        $h = 0;
        foreach ($this->state->getContentItems() as $contentItem) {
            $h += max(1, $contentItem->height);
        }
        return $h;
    }

    /**
     * Given a line offset from top, return [cardIndex, skipLinesInsideThatCard]
     */
    private function locateStartCard(int $lineOffset): array
    {
        $acc = 0;
        foreach ($this->state->getContentItems() as $i => $contentItem) {
            $h = max(1, $contentItem->height);
            if ($lineOffset < $acc + $h) {
                return [$i, $lineOffset - $acc];
            }
            $acc += $h;
        }
        // past end: show last card from its last line
        return [max(0, count($this->state->getContentItems()) - 1), 0];
    }

    /**
     * Collect visible slice (cards + partial first card) that fits into viewportH lines.
     * @return list<array{item:ContentItem,height:int}>
     */
    private function collectVisible(int $startIdx, int $skipInFirst, int $viewportH): array
    {
        $left = $viewportH;
        $out  = [];

        for ($i = $startIdx; $i < count($this->state->getContentItems()) && $left > 0; $i++) {
            $contentItem = $this->state->getContentItems()[$i];
            $height = max(1, $contentItem->height);

            if ($i === $startIdx && $skipInFirst > 0) {
                // We need to trim top lines of the first card. For simplicity,
                // we keep full Text but reduce the accounted height.
                $visibleH = max(0, $height - $skipInFirst);
                if ($visibleH <= 0) continue;
                $take = min($left, $visibleH);
                $out[] = [
                    'item' => $contentItem,
                    'height' => min($left, $visibleH)
                ];
                $left -= $take;
            } else {
                $out[] = [
                    'item' => $contentItem,
                    'height' => min($left, $height)
                ];
                $take = min($left, $height);
                $left -= $take;
            }
        }

        // If nothing, push an empty spacer to keep block height sane
        if ($out === []) {
            $out[] = [
                'item' => ContentItemFactory::make(ContentItemFactory::EMPTY_ITEM),
                'height' => 1
            ];
        }
        return $out;
    }


}
