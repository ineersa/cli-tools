<?php

namespace App\Tui\Utility;

use App\Tui\Component\AutocompleteComponent;
use App\Tui\Component\InputComponent;
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

    public static function moveCursorToInputBox(Terminal $terminal, int $caretLine, int $caretCol , int $scrollTopLine): void
    {
        $inputBoxTop    = 30 + 1 + AutocompleteComponent::MAX_ROWS_VISIBLE + 1; // help=1 + History = 25 + Dynamic Island 10
        $inputBoxOffset = 1; // border inside offset
        $row = $inputBoxTop + $inputBoxOffset + ($caretLine - $scrollTopLine);
        $col = 1 + 1 + $caretCol; // +1 for left border + 1 for padding

        $terminal->execute(Actions::moveCursor($row+1, $col+1)); // 1-based coords
    }
}
