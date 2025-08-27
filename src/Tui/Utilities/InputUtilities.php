<?php

namespace App\Tui\Utilities;

class InputUtilities
{
    /**
     * Soft-wrap $text to $width and compute caret (line,col) in the wrapped output.
     * @return array{0:list<string>,1:int,2:int} [$lines, $caretLine, $caretCol]
     */
    public static function wrapTextAndLocateCaret(string $text, int $caretIdx, int $width): array
    {
        $width = max(1, $width);

        // simulate up to caret to find wrapped caret position
        $line = 0;
        $col = 0;
        $n = strlen($text);

        for ($i = 0; $i < $caretIdx; $i++) {
            $ch = $text[$i];
            if ($ch === "\n") {
                $line++;
                $col = 0;
            } else {
                $col++;
                if ($col >= $width) {
                    $line++;
                    $col = 0;
                }
            }
        }
        $cLine = $line;
        $cCol = $col;

        // build wrapped lines for full text
        $out = [];
        $buf = '';
        $col = 0;
        for ($i = 0; $i < $n; $i++) {
            $ch = $text[$i];
            if ($ch === "\n") {
                $out[] = $buf;
                $buf = '';
                $col = 0;

                continue;
            }
            $buf .= $ch;
            $col++;
            if ($col >= $width) {
                $out[] = $buf;
                $buf = '';
                $col = 0;
            }
        }
        $out[] = $buf; // push last (even if empty)
        if ($out === []) {
            $out = [''];
        }

        return [$out, $cLine, $cCol];
    }

    public static function sanitizePaste(string $text): string
    {
        // Keep printable ASCII, tabs, newlines; normalize CRLF -> LF
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $out = '';
        $n = \strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $c = $text[$i];
            $o = \ord($c);
            if ($c === "\n" || $c === "\t" || ($o >= 32 && $o <= 126)) {
                $out .= $c;
            }
        }

        return $out;
    }
}
