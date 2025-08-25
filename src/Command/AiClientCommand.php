<?php

declare(strict_types=1);

namespace App\Command;

use App\Input\InputProcessor;
use App\Render\Layout;
use App\Render\RenderProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

#[AsCommand(
    name: 'ai:client',
    description: 'REPL with header, history cards (content-width), boxed input, autocomplete, and status bar.',
)]
final class AiClientCommand extends Command
{

    /** @var list<array{name:string, desc:string}> */
    private array $commands = [
        ['name' => '/about',   'desc' => 'show version info'],
        ['name' => '/auth',    'desc' => 'change the auth method'],
        ['name' => '/chat',    'desc' => 'Manage conversation history.'],
        ['name' => '/clear',   'desc' => 'clear the screen and history'],
        ['name' => '/copy',    'desc' => 'Copy the last result to clipboard'],
        ['name' => '/exit',    'desc' => 'quit'],
    ];

    // Autocomplete UI state
    private bool $acVisible = false;
    private int  $acCursor  = 0;
    private int  $acMaxRows = 8;
    /** @var list<array{name:string, desc:string}> */
    private array $acFiltered = [];

    // Raw mode flag
    private bool $raw = false;

    // Tracks how far we lifted the real cursor above the bottom (status) last repaint.
    private int $lastLift = 0;

    // --- render caches (content + line counts) ---
    private string $prevHistory = '';
    private string $prevInput   = '';
    private string $prevAc      = '';
    private string $prevStatus  = '';
    private int $linesHistory   = 0;
    private int $linesInput     = 0;
    private int $linesAc        = 0;
    private int $linesStatus    = 0;

    // caret memo
    private int $lastCaretUpFromInputBottom = 0;
    private int $lastCaretCol = 1;

    // Reused stdin handle
    /** @var resource|null */
    private $stdin = null;

    /**
     * @param InputInterface $input
     * @param ConsoleOutput $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputProcessor = new InputProcessor();
        $inputProcessor->enterRawMode();
        $renderProcessor = new RenderProcessor($output);
        $renderProcessor->initLayout();


        try {
            while (true) {
                $key = $inputProcessor->readKey();

                // Quit
                if ($key === 'CTRL_C' || $key === 'CTRL_D') {
                    break;
                }

                // Multiline
                if ($key === 'CTRL_N') {
                    $buffer .= "\n";
                    $this->updateAutocompleteState($buffer);
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['input'=>true,'ac'=>true]);
                    continue;
                }

                // ENTER: accept AC or submit
                if ($key === 'ENTER') {
                    if ($this->acVisible && $this->acFiltered !== []) {
                        $chosen = $this->acFiltered[$this->acCursor]['name'];
                        $buffer = $chosen . ' ';
                        $this->updateAutocompleteState($buffer); // hides due to space
                        $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['input'=>true,'ac'=>true]);
                        continue;
                    }

                    $line = trim($buffer);

                    if ($line === '/exit') {
                        break;
                    }
                    if ($line === '/clear') {
                        $this->history = [];
                        $buffer = '';
                        $this->updateAutocompleteState($buffer);
                        $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['history'=>true,'input'=>true,'ac'=>true]);
                        continue;
                    }

                    if ($line !== '') {
                        $variant = (str_starts_with($line, '/')) ? 'command' : 'user';
                        $this->pushHistory('you', $line, $variant);
                        // demo assistant response
                        $this->pushHistory('assistant', "You said: <info>{$line}</info>", 'assistant');
                    }

                    $buffer = '';
                    $this->updateAutocompleteState($buffer);
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['history'=>true,'input'=>true,'ac'=>true]);
                    continue;
                }

                // TAB: accept AC
                if ($key === 'TAB' && $this->acVisible && $this->acFiltered !== []) {
                    $chosen = $this->acFiltered[$this->acCursor]['name'];
                    $buffer = $chosen . ' ';
                    $this->updateAutocompleteState($buffer); // hides due to space
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['input'=>true,'ac'=>true]);
                    continue;
                }

                // UP/DOWN: navigate AC
                if ($key === 'UP' && $this->acVisible && $this->acFiltered !== []) {
                    $this->acCursor = max(0, $this->acCursor - 1);
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['ac'=>true]);
                    continue;
                }
                if ($key === 'DOWN' && $this->acVisible && $this->acFiltered !== []) {
                    $this->acCursor = min(count($this->acFiltered) - 1, $this->acCursor + 1);
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['ac'=>true]);
                    continue;
                }

                // BACKSPACE
                if ($key === 'BACKSPACE') {
                    $buffer = mb_substr($buffer, 0, max(0, mb_strlen($buffer) - 1));
                    $this->updateAutocompleteState($buffer);
                    $this->repaintSelective($history, $inputBox, $autocomplete, $status, $buffer, ['input'=>true,'ac'=>true]);
                    continue;
                }

                // Printable
                if ($key === 'CHAR') {
                    $renderProcessor->processInputBox($inputProcessor->getInputBuffer());

                    continue;
                }

                // No-op key
            }
        } finally {
            // Always leave raw mode first so the terminal behaves normally
            $inputProcessor->leaveRawMode();
            $inputProcessor->closeStdin();
            // Move caret to bottom (in case we were "lifted") to avoid ghost lines
            $renderProcessor->parkCursorAt(999, 1);
            $renderProcessor->clearTerminalCompletely();

            $renderProcessor->clearTerminalCompletely();
        }

        return Command::SUCCESS;
    }

    // ----------------------------- Rendering -------------------------------

    /**
     * Full-width input box (purple) as string. Returns [string, caretUp, caretCol].
     *
     * @return array{0:string,1:int,2:int}
     */
    private function renderInputBoxString(string $buffer): array
    {
        $termWidth = max(20, (new Terminal())->getWidth() ?? 100);
        $inner = $termWidth - 2;

        $lines = $buffer === '' ? [''] : explode("\n", $buffer);

        $promptVisible = '> ';
        the_cont:
        $contPrefix    = '  ';

        $rendered = [];
        $rendered[] = '<fg=magenta>┌' . str_repeat('─', $inner) . '┐</>';

        foreach ($lines as $i => $l) {
            $prefix = $i === 0 ? $promptVisible : $contPrefix;
            $content = $l;
            if ($content === '' && $i === 0) {
                $content = '<fg=gray>Type your message or @path/to/file</>';
            }

            $visiblePrefixLen  = $this->vlen($prefix);
            $maxContent = max(0, $inner - 1 - $visiblePrefixLen - 1);
            if ($this->vlen($content) > $maxContent) {
                $content = $this->vsubstr($content, 0, $maxContent);
            }

            $lineVisibleLen = 1 + 1 + $visiblePrefixLen + $this->vlen($content) + 1;
            $padding = max(0, $inner - ($lineVisibleLen - 1));

            $rendered[] = '<fg=magenta>│</> ' . '<options=bold>' . $prefix . '</>' . $content . str_repeat(' ', $padding + 1) . '<fg=magenta>│</>';
        }

        $rendered[] = '<fg=magenta>└' . str_repeat('─', $inner) . '┘</>';

        // Caret on the last content line
        $lastLine  = end($lines) ?: '';
        $isFirst   = (count($lines) === 1);
        $prefixLen = $this->vlen($isFirst ? $promptVisible : $contPrefix);

        // left frame '│' + space = 2; then prefix + content; +1 to sit *after* last char
        $col = 2 + $prefixLen + $this->vlen($lastLine) + 1;

        // keep caret within the inner frame
        $col = max(1, min($col, $inner + 2));

        // We need to move 2 lines up: bottom border + last content line
        return [implode(PHP_EOL, $rendered), 2, $col];
    }

    /**
     * In-place rewrite of the input box without collapsing the section, but only
     * when the number of lines is unchanged. Uses Symfony's formatter so tags render.
     *
     * @return int the number of lines rendered
     */
    private function rewriteInputBoxInPlaceWithFormatter(ConsoleSectionOutput $section, string $newInput): int
    {
        $formatter = $section->getFormatter();
        $newLines  = ($newInput === '') ? [] : explode(PHP_EOL, $newInput);

        // Move cursor to TOP of current input box from bottom: status + ac + input lines
        $topOffset = $this->linesStatus + $this->linesAc + $this->linesInput;
        if ($topOffset > 0) echo "\033[{$topOffset}A";
        echo "\033[1G";

        // Hide cursor for the operation
        echo "\033[?25l";

        // Overwrite each existing line in place
        $rows = $this->linesInput; // equal to count($newLines) when this function is called
        for ($i = 0; $i < $rows; $i++) {
            $line = $newLines[$i] ?? '';
            $line = $formatter->format($line);   // <<< use Symfony formatter
            echo "\r\033[K";                     // clear to EOL
            echo $line;
            if ($i < $rows - 1) echo PHP_EOL;
        }

        // Return caret to bottom (where we started): we moved up $topOffset, and printed (rows-1) newlines.
        $down = $topOffset - ($rows - 1);
        if ($down > 0) echo "\033[{$down}B";

        echo "\033[?25h";
        flush();

        return count($newLines);
    }

    private function renderAutocompleteString(): string
    {
        if (!$this->acVisible || $this->acFiltered === []) return '';
        $total = count($this->acFiltered);
        $start = intdiv($this->acCursor, $this->acMaxRows) * $this->acMaxRows;
        $end   = min($total, $start + $this->acMaxRows);

        $rows = [];
        for ($i = $start; $i < $end; $i++) {
            $item = $this->acFiltered[$i];
            $isSel = ($i === $this->acCursor);
            $name = $isSel ? "<options=reverse>{$item['name']}</>" : $item['name'];
            $rows[] = sprintf('%-16s  <fg=gray>%s</>', $name, $item['desc']);
        }

        if ($total > $this->acMaxRows) {
            $page = intdiv($this->acCursor, $this->acMaxRows) + 1;
            $pages = (int)ceil($total / $this->acMaxRows);
            $rows[] = "<fg=gray>(${page}/{$pages})</>";
        }

        return implode(PHP_EOL, $rows);
    }







    private function pushHistory(string $role, string $text, string $variant): void
    {
        $this->history[] = ['role' => $role, 'text' => $text, 'variant' => $variant];
    }

    // ------------------------- Raw mode + keys -----------------------------



    // ------------------------ string utils (ANSI-safe-ish) ----------------

    private function vlen(string $s): int
    {
        return strlen($this->vstrip($s));
    }

    private function vstrip(string $s): string
    {
        $noTags = strip_tags($s);
        return preg_replace('/\e\[[\d;]*m/', '', $noTags) ?? $noTags;
    }

    private function vsubstr(string $s, int $start, int $len): string
    {
        return substr($this->vstrip($s), $start, $len);
    }

    private function mb_substr_safe(string $s, int $start, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($s, $start, $length) : substr($s, $start, $length);
    }
}
