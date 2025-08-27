<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use App\Tui\Utilities\InputUtilities;
use App\Tui\Utilities\TerminalUtilities;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Display\Area;
use PhpTui\Tui\Display\Buffer;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Position\Position;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

final class InputComponent implements Component
{
    private bool $editing = true;        // always focused
    private int $stickyCol = 0;          // preferred column for up/down (hard lines)
    public const MAX_VISIBLE_LINES = 5; // input viewport height (wrapped)

    /** @var list<string> */
    private array $messages = [];

    private ?float $lastEscTime = null;

    public function __construct(
        private State $state,
        private Terminal $terminal,
    ) {}

    public function build(): Widget
    {
        // Input (soft-wrapped viewport)
        if ($this->state->getInput() === '') {
            $placeholderSpan = Span::fromString('You can include files with @')
                ->style(Style::default()->fg(AnsiColor::LightBlue));
            $inputParaWidget = ParagraphWidget::fromSpans($placeholderSpan);
        } else {
            $inputView = $this->renderInputViewport();
            $inputStyle = $this->state->isEditing() ? Style::default()->yellow() : Style::default();
            $inputParaWidget = ParagraphWidget::fromText(Text::fromString($inputView))
                ->style($inputStyle);
        }
        return BlockWidget::default()
            ->borders(Borders::ALL)
            ->style(Style::default()->fg(AnsiColor::Magenta))
            ->titles()
            ->padding(Padding::horizontal(1))
            ->widget($inputParaWidget);
    }

    public function handle(Event $event): void
    {
        if ($event instanceof CharKeyEvent) {
            if ($event->char === 'y' && $event->modifiers === KeyModifiers::CONTROL) {
                $this->deleteCurrentLine();
                return;
            }
            // Accept multi-char bursts (paste), keep ASCII & newlines
            $chunk = InputUtilities::sanitizePaste($event->char);
            if ($chunk !== '') {
                $this->insertText($chunk);
                $this->updateStickyFromIndex();
            }
        } elseif ($event instanceof CodedKeyEvent) {
            $code = $event->code;

            if ($this->state->isEditing()) {
                if ($code === KeyCode::Enter) {
                    // Enter inserts newline (not submit)
                    $this->insertText("\n");
                    $this->updateStickyFromIndex();
                } elseif ($code === KeyCode::Backspace) {
                    $this->deleteCharLeft();
                    $this->updateStickyFromIndex();
                } elseif ($code === KeyCode::Left) {
                    $this->moveLeft();
                    $this->updateStickyFromIndex();
                } elseif ($code === KeyCode::Right) {
                    $this->moveRight();
                    $this->updateStickyFromIndex();
                } elseif ($code === KeyCode::Up) {
                    $this->moveUp();
                } elseif ($code === KeyCode::Down) {
                    $this->moveDown();
                } elseif ($code === KeyCode::Esc) {
                    $now = microtime(true);
                    if ($this->lastEscTime !== null && ($now - $this->lastEscTime) < 0.5) {
                        $this->clearAll(); // double ESC
                    }
                    $this->lastEscTime = $now;
                }
            }
        }
    }

    private function renderInputViewport(): string
    {
        [$wrapped,,] =
            InputUtilities::wrapTextAndLocateCaret(
                $this->state->getInput(),
                $this->state->getCharIndex(),
                TerminalUtilities::getTerminalInnerWidth($this->terminal)
            );

        $start = $this->state->getScrollTopLine();
        $end = min(count($wrapped), $start + self::MAX_VISIBLE_LINES);

        $out = [];
        for ($i = $start; $i < $end; $i++) {
            $ln = $wrapped[$i] ?? '';
            $out[] = $ln;
        }

        return implode("\n", $out);
    }

    private function insertText(string $text): void
    {
        [$l, $r] = $this->splitAtCharIndex($this->state->getCharIndex());
        $this->state->setInput($l . $text . $r);
        $this->state->setCharIndex($this->state->getCharIndex() + \mb_strlen($text));
        $this->ensureCaretVisible();
    }

    private function splitAtCharIndex(int $idx): array
    {
        $idx = max(0, min($idx, \mb_strlen($this->state->getInput())));

        return [\substr($this->state->getInput(), 0, $idx), \substr($this->state->getInput(), $idx)];
    }

    private function ensureCaretVisible(): void
    {
        [$wrapped, $cLine, ] = InputUtilities::wrapTextAndLocateCaret(
            $this->state->getInput(),
            $this->state->getCharIndex(),
            TerminalUtilities::getTerminalInnerWidth($this->terminal)
        );

        $max = self::MAX_VISIBLE_LINES;
        if ($cLine < $this->state->getScrollTopLine()) {
            $this->state->setScrollTopLine($cLine);
        } elseif ($cLine >= $this->state->getScrollTopLine() + $max) {
            $this->state->setScrollTopLine($cLine - $max + 1);
        }

        $total = count($wrapped);
        $this->state->setScrollTopLine(max(0, min($this->state->getScrollTopLine(), max(0, $total - $max))));
    }

    private function deleteCharLeft(): void
    {
        if ($this->state->getCharIndex() === 0) {
            return;
        }
        [$l, $r] = $this->splitAtCharIndex($this->state->getCharIndex());
        $l = \substr($l, 0, \strlen($l) - 1);
        $this->state->setInput($l . $r);
        $this->state->setCharIndex($this->state->getCharIndex() - 1);
        $this->ensureCaretVisible();
    }

    private function moveLeft(): void
    {
        $this->state->setCharIndex(max(0, $this->state->getCharIndex() - 1));
        $this->ensureCaretVisible();
    }

    private function moveRight(): void
    {
        $this->state->setCharIndex(min(\mb_strlen($this->state->getInput()), $this->state->getCharIndex() + 1));
        $this->ensureCaretVisible();
    }

    private function moveUp(): void
    {
        [$lineStarts, $lines] = $this->lineIndexing();
        [$curLine, $curCol] = $this->cursorLineCol($lineStarts);

        $this->stickyCol = $this->stickyCol ?: $curCol;
        $targetLine = max(0, $curLine - 1);
        $targetCol = min($this->stickyCol, \strlen($lines[$targetLine] ?? ''));
        $this->state->setCharIndex(($lineStarts[$targetLine] ?? 0) + $targetCol);

        $this->ensureCaretVisible();
    }

    private function moveDown(): void
    {
        [$lineStarts, $lines] = $this->lineIndexing();
        [$curLine, $curCol] = $this->cursorLineCol($lineStarts);

        $this->stickyCol = $this->stickyCol ?: $curCol;
        $targetLine = min(count($lines) - 1, $curLine + 1);
        $targetCol = min($this->stickyCol, \strlen($lines[$targetLine] ?? ''));
        $this->state->setCharIndex( ($lineStarts[$targetLine] ?? 0) + $targetCol);

        $this->ensureCaretVisible();
    }

    private function updateStickyFromIndex(): void
    {
        [$lineStarts,] = $this->lineIndexing();
        [, $col] = $this->cursorLineCol($lineStarts);
        $this->stickyCol = $col;
    }

    /**
     * @return array{0: list<int>, 1: list<string>}
     *   lineStarts: char-index of each line start (hard lines)
     *   lines:      lines split by "\n" (ASCII)
     */
    private function lineIndexing(): array
    {
        $lines = \explode("\n", $this->state->getInput());
        $starts = [];
        $idx = 0;
        foreach ($lines as $i => $line) {
            $starts[$i] = $idx;
            // +1 for newline except after last line
            $idx += \strlen($line) + 1;
        }
        if ($lines === []) {
            $lines = [''];
            $starts = [0];
        }
        if (!str_ends_with($this->state->getInput(), "\n")) {
            $idx--; // cancel the last +1
        }

        return [$starts, $lines];
    }

    /**
     * Convert absolute charIndex -> (line, col) in hard-line space.
     * @param list<int> $lineStarts
     * @return array{0:int,1:int}
     */
    private function cursorLineCol(array $lineStarts): array
    {
        $line = 0;
        for ($i = 0; $i < count($lineStarts); $i++) {
            if ($lineStarts[$i] > $this->state->getCharIndex()) {
                break;
            }
            $line = $i;
        }
        $col = $this->state->getCharIndex() - $lineStarts[$line];

        return [$line, $col];
    }

    private function deleteCurrentLine(): void
    {
        [$lineStarts, $lines] = $this->lineIndexing();
        [$curLine, ] = $this->cursorLineCol($lineStarts);

        $before = substr($this->state->getInput(), 0, $lineStarts[$curLine]);
        $after = '';
        if (isset($lines[$curLine + 1])) {
            // if not the last line, preserve the following lines (with \n)
            $after = substr($this->state->getInput(), $lineStarts[$curLine] + strlen($lines[$curLine]) + 1);
        }

        $this->state->setInput($before . $after);
        $this->state->setCharIndex($lineStarts[$curLine]); // move the caret to start of this line
        $this->ensureCaretVisible();
    }

    public function clearAll(): void
    {
        $this->state->setInput('');
        $this->state->setCharIndex(0);
        $this->stickyCol = 0;
        $this->state->setScrollTopLine(0);
    }
}
