<?php

// src/Util/Clipboard.php
declare(strict_types=1);

namespace App\Tui\Utility;

use Symfony\Component\Process\Process;

final class Clipboard
{
    public static function copy(string $text): bool
    {
        // Fast path: external tools (works inside tmux too)
        if (\PHP_OS_FAMILY === 'Darwin' && self::runPipe(['pbcopy'], $text)) {
            return true;
        }
        if (\PHP_OS_FAMILY === 'Windows' && self::runPipe(['cmd', '/c', 'clip'], $text)) {
            return true;
        }

        if (self::commandExists('wl-copy') && self::runPipe(['wl-copy'], $text)) {
            return true;
        }
        if (self::commandExists('xclip') && self::runPipe(['xclip', '-selection', 'clipboard'], $text)) {
            return true;
        }
        if (self::commandExists('xsel') && self::runPipe(['xsel', '--clipboard', '--input'], $text)) {
            return true;
        }

        // tmux: try tmux buffer (internal) then OSC-52 passthrough
        if (getenv('TMUX')) {
            // 1) tmux internal buffer (may or may not reach system clipboard depending on tmux config)
            if (self::runPipe(['tmux', 'load-buffer', '-'], $text)) {
                // Best-effort: also push to host clipboard via OSC-52 passthrough
                if (self::osc52ToStdout($text, true)) {
                    return true;
                }

                return true; // at least in tmux buffer
            }
            // 2) Direct OSC-52 passthrough to the outer terminal
            if (self::osc52ToStdout($text, true)) {
                return true;
            }
        }

        // Non-tmux: plain OSC-52 to terminal
        if (self::osc52ToStdout($text, false)) {
            return true;
        }

        return false;
    }

    private static function osc52ToStdout(string $text, bool $wrapForTmux): bool
    {
        // Empty string clears clipboard per OSC-52; if you prefer no-op on empty:
        // if ($text === '') return false;

        $b64 = base64_encode($text);
        $osc = "\x1b]52;c;{$b64}\x07"; // BEL-terminated is broadly supported

        // If we’re inside tmux, wrap with DCS passthrough so the host terminal sees it:
        // ESC P tmux; ESC <osc> ESC \
        if ($wrapForTmux) {
            $osc = "\x1bPtmux;\x1b".$osc."\x1b\\";
        }

        // Write to STDOUT if it’s a TTY so the terminal processes it
        $isTty = \function_exists('posix_isatty') ? @posix_isatty(\STDOUT) : true;
        if ($isTty) {
            // NB: don’t add newline; terminals parse the escape as-is
            fwrite(\STDOUT, $osc);
            fflush(\STDOUT);

            return true;
        }

        return false;
    }

    /**
     * @param string[] $cmd
     * @param string $input
     * @return bool
     */
    private static function runPipe(array $cmd, string $input): bool
    {
        try {
            $p = new Process($cmd);
            $p->setInput($input);
            $p->run();

            return $p->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function commandExists(string $cmd): bool
    {
        try {
            $which = new Process(['which', $cmd]);
            $which->run();

            return $which->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
