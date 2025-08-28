<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use App\Tui\Utility\InputUtilities;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

final class InputComponent implements Component
{
    public const MAX_VISIBLE_LINES = 5; // input viewport height (wrapped)
    private ?float $lastEscTime = null;

    public function __construct(
        private State $state,
        private Terminal $terminal,
    ) {
    }

    public function build(): Widget
    {
        // Input (soft-wrapped viewport)
        if ('' === $this->state->getInput()) {
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
            if ('y' === $event->char && KeyModifiers::CONTROL === $event->modifiers) {
                $this->deleteCurrentLine();

                return;
            }
        } elseif ($event instanceof CodedKeyEvent) {
            $code = $event->code;

            if ($this->state->isEditing()) {
                if (KeyCode::Enter === $code) {
                    // If AC is open, we don't add new line
                    if ($this->state->isAcOpen()) {
                        return;
                    }
                    // Enter inserts newline (not submit)
                    InputUtilities::insertText("\n", $this->state);
                    InputUtilities::ensureCaretVisible($this->state, $this->terminal);
                    InputUtilities::updateStickyFromIndex($this->state);
                } elseif (KeyCode::Backspace === $code) {
                    $this->deleteCharLeft();
                    InputUtilities::updateStickyFromIndex($this->state);
                } elseif (KeyCode::Left === $code) {
                    $this->moveLeft();
                    InputUtilities::updateStickyFromIndex($this->state);
                } elseif (KeyCode::Right === $code) {
                    $this->moveRight();
                    InputUtilities::updateStickyFromIndex($this->state);
                } elseif (KeyCode::Up === $code) {
                    $this->moveUp();
                } elseif (KeyCode::Down === $code) {
                    $this->moveDown();
                } elseif (KeyCode::Esc === $code) {
                    $now = microtime(true);
                    if (null !== $this->lastEscTime && ($now - $this->lastEscTime) < 0.5) {
                        $this->clearAll(); // double ESC
                    }
                    $this->lastEscTime = $now;
                }
            }
        }
    }

    public function clearAll(): void
    {
        $this->state->setInput('');
        $this->state->setCharIndex(0);
        $this->state->setStickyCol(0);
        $this->state->setScrollTopLine(0);
    }

    private function renderInputViewport(): string
    {
        [$wrapped] =
            InputUtilities::wrapTextAndLocateCaret(
                $this->state->getInput(),
                $this->state->getCharIndex(),
                TerminalUtilities::getTerminalInnerWidth($this->terminal)
            );

        $start = $this->state->getScrollTopLine();
        $end = min(\count($wrapped), $start + self::MAX_VISIBLE_LINES);

        $out = [];
        for ($i = $start; $i < $end; ++$i) {
            $ln = $wrapped[$i] ?? '';
            $out[] = $ln;
        }

        return implode("\n", $out);
    }

    private function deleteCharLeft(): void
    {
        if (0 === $this->state->getCharIndex()) {
            return;
        }
        [$l, $r] = InputUtilities::splitAtCharIndex($this->state);
        $l = substr($l, 0, \strlen($l) - 1);
        $this->state->setInput($l.$r);
        $this->state->setCharIndex($this->state->getCharIndex() - 1);
        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }

    private function moveLeft(): void
    {
        $this->state->setCharIndex(max(0, $this->state->getCharIndex() - 1));
        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }

    private function moveRight(): void
    {
        $this->state->setCharIndex(min(mb_strlen($this->state->getInput()), $this->state->getCharIndex() + 1));
        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }

    private function moveUp(): void
    {
        [$lineStarts, $lines] = InputUtilities::lineIndexing($this->state);
        [$curLine, $curCol] = InputUtilities::cursorLineCol($lineStarts, $this->state);

        $sticky = $this->state->getStickyCol();
        $sticky = $sticky ?: $curCol;
        $this->state->setStickyCol($sticky);
        $targetLine = max(0, $curLine - 1);
        $targetCol = min($sticky, \strlen($lines[$targetLine] ?? ''));
        $this->state->setCharIndex(($lineStarts[$targetLine] ?? 0) + $targetCol);

        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }

    private function moveDown(): void
    {
        [$lineStarts, $lines] = InputUtilities::lineIndexing($this->state);
        [$curLine, $curCol] = InputUtilities::cursorLineCol($lineStarts, $this->state);

        $this->state->setStickyCol($this->state->getStickyCol() ?: $curCol);
        $targetLine = min(\count($lines) - 1, $curLine + 1);
        $targetCol = min($this->state->getStickyCol(), \strlen($lines[$targetLine] ?? ''));
        $this->state->setCharIndex(($lineStarts[$targetLine] ?? 0) + $targetCol);
        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }

    private function deleteCurrentLine(): void
    {
        [$lineStarts, $lines] = InputUtilities::lineIndexing($this->state);
        [$curLine] = InputUtilities::cursorLineCol($lineStarts, $this->state);

        $before = substr($this->state->getInput(), 0, $lineStarts[$curLine]);
        $after = '';
        if (isset($lines[$curLine + 1])) {
            // if not the last line, preserve the following lines (with \n)
            $after = substr($this->state->getInput(), $lineStarts[$curLine] + \strlen($lines[$curLine]) + 1);
        }

        $this->state->setInput($before.$after);
        $this->state->setCharIndex($lineStarts[$curLine]); // move the caret to start of this line
        InputUtilities::ensureCaretVisible($this->state, $this->terminal);
    }
}
