<?php

declare(strict_types=1);

namespace App\Tui\Utility;

use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\HelpStringComponent;
use App\Tui\Component\WindowedContentComponent;
use PhpTui\Term\Actions;
use PhpTui\Term\Terminal;
use PhpTui\Term\TerminalInformation\Size;

class TerminalUtilities
{
    public static function getTerminalInnerWidth(Terminal $terminal, int $borders = 4): int
    {
        $size = $terminal->info(Size::class);

        // 2 columns for left/right borders
        return max(1, $size->cols - $borders);
    }

    public static function moveCursorToInputBox(Terminal $terminal, int $caretLine, int $caretCol, int $scrollTopLine): void
    {
        $inputBoxTop = WindowedContentComponent::CONTENT_HEIGHT + HelpStringComponent::HEIGHT + AutocompleteComponent::MAX_ROWS_VISIBLE + 1;
        $inputBoxOffset = 1; // border inside offset
        $row = $inputBoxTop + $inputBoxOffset + ($caretLine - $scrollTopLine);
        $col = 1 + 1 + $caretCol; // +1 for left border + 1 for padding

        $terminal->execute(Actions::moveCursor($row + 1, $col + 1)); // 1-based coords
    }
}
