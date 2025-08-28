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
use PhpTui\Tui\Color\RgbColor;
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

    /** top-of-viewport line offset from the very start (0 = top) */
    private int $scrollY = 0;

    /** true if user scrolled up; new cards won’t auto-stick to bottom */
    private bool $pinnedUp = false;


    public function __construct(private State $state, private Terminal $terminal) {}

    public function build(): Widget
    {
        $viewportH = max(1, $this->state->getContentViewportHeight());
        $viewportW = max(10, TerminalUtilities::getTerminalInnerWidth($this->terminal));

        // 1) Measure any cards with unknown height for this width
        foreach ($this->state->getContentItems() as $contentItem) {
            if ($contentItem->height === 0) {
                $contentItem->height = $this->measureTextHeight($contentItem, $viewportW);
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

        foreach ($visible as $row) {
            $ci       = $row['item'];
            $take     = $row['height'];          // render height (text + borders taken)
            $skipText = $row['skipText'] ?? 0;

            $pw = ParagraphWidget::fromLines(...$ci->text->lines)->style($ci->style);
            if ($skipText > 0) {
                $pw->scroll = [$skipText, 0]; // scroll within TEXT, borders unaffected
            }

            $constraints[] = Constraint::length($take);

            if ($ci->hasBorders) {
                $card = BlockWidget::default()
                    ->borders(Borders::ALL)
                    ->widget($pw);

                if ($ci->title)      { $card->titles(Title::fromString($ci->title)); }
                if ($ci->titleStyle) { $card->titleStyle($ci->titleStyle); }
                if ($ci->borderColorHex) {
                    $card->borderStyle(Style::default()->fg(RgbColor::fromHex($ci->borderColorHex)));
                }
                $widgets[] = $card;
            } else {
                $widgets[] = $pw;
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

        $page = max(1, $this->state->getContentViewportHeight() - 1);
        $maxTop = max(0, $this->totalHeight() - max(1, $this->state->getContentViewportHeight()));

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

    private function measureTextHeight(ContentItem $contentItem, int $width): int
    {
        // naive wrapping: count "soft" line wraps by splitting spans to plain strings.
        $lines = 0;
        // account borders
        if ($contentItem->hasBorders) {
            $width -= 2;
        }
        foreach ($contentItem->text->lines as $line) {
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
            if ($contentItem->hasBorders) {
                $h += 2; // borders
            }
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
            $textH  = max(1, $contentItem->height);
            $chrome = $contentItem->hasBorders ? 2 : 0;
            $renderH = $textH + $chrome;

            if ($lineOffset < $acc + $renderH) {
                $offsetInCard = $lineOffset - $acc; // 0..renderH-1
                $skipText = $offsetInCard - ($contentItem->hasBorders ? 1 : 0); // eat top border
                $skipText = max(0, min($skipText, $textH - 1));
                return [$i, $skipText];
            }
            $acc += $renderH;
        }
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
            $ci        = $this->state->getContentItems()[$i];
            $textH     = max(1, $ci->height);
            $hasBorder = $ci->hasBorders;

            $skipText  = ($i === $startIdx) ? max(0, $skipInFirst) : 0;
            $remainText = max(0, $textH - $skipText);

            // visible chrome: normally 2 (top+bottom) for bordered cards,
            // but if the top border has scrolled off (first card with skipText>0),
            // only the bottom border remains visible -> 1.
            $visibleChrome = 0;
            if ($hasBorder) {
                $visibleChrome = ($i === $startIdx && $skipText > 0) ? 1 : 2;
            }

            // how many render lines can we take?
            $takeText   = min($remainText, max(0, $left - $visibleChrome));
            $takeRender = $visibleChrome + $takeText;

            // edge: not even enough space for visible chrome
            if ($left < $visibleChrome) {
                $takeRender = $left;
                $takeText   = 0;
            }

            if ($takeRender <= 0) break;

            $out[] = ['item' => $ci, 'height' => $takeRender, 'skipText' => $skipText];
            $left -= $takeRender;
        }

        if ($out === []) {
            $out[] = ['item' => ContentItemFactory::make(ContentItemFactory::EMPTY_ITEM), 'height' => 1, 'skipText' => 0];
        }
        return $out;
    }


}
