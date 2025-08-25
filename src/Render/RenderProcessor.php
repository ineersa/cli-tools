<?php

namespace App\Render;

use App\Render\Section\AutoCompleteSection;
use App\Render\Section\HistorySection;
use App\Render\Section\InputBoxSection;
use App\Render\Section\StatusSection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class RenderProcessor
{
    private Layout $layout;

    public function __construct(
        private OutputInterface $output,
    )
    {

    }

    public function initLayout()
    {
        $this->layout = new Layout($this->output, $this);
        $this->layout->init();
    }

    public function processInputBox(string $buffer): void
    {
        $rows = ((new Terminal())->getHeight() ?? 999) - 1;
        echo "\033[?25l";              // hide cursor
        echo "\033[{$rows};1H";        // CUP: absolute row=bottom, col=1

        /** @var AutoCompleteSection $ac */
        $ac = $this->layout->getSection(AutoCompleteSection::class);
        if ($ac->updateState($buffer)) {
            $ac->render();
        }

        /** @var InputBoxSection $input */
        $input = $this->layout->getSection(InputBoxSection::class);
        $input->render($buffer);

        /** @var StatusSection $status */
        $status = $this->layout->getSection(StatusSection::class);
        $status->render(); // LAST → real cursor sits at bottom

        $lift = 2 + $ac->getLinesAc(); // == 2 + linesAc
        echo "\033[{$lift}A\033[{$input->getCaretColumn()}G";

        echo "\033[?25h"; // show cursor
        flush();
    }

    /**
     * @param int $row
     * @param int $column
     * @param string $direction A = up relative B = down relative
     * @return void
     */
    public function parkCursorAt(int $row, int $column, string $direction = "B"): void
    {
        echo "\033[$row$direction\033[{$column}G";
    }

    /**
     * Wipe the whole screen (including header + scrollback)
     * @return void
     */
    public function clearTerminalCompletely(): void
    {
        $this->layout->clearSection(HistorySection::class);
        $this->layout->clearSection(InputBoxSection::class);
        $this->layout->clearSection(AutoCompleteSection::class);
        $this->layout->clearSection(StatusSection::class);

        // Always ensure cursor visible & styles reset
        echo "\033[?25h\033[0m";

        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows: fall back to CLS
            // (Sections were already cleared; this wipes the screen)
            @shell_exec('cls');
            return;
        }

        // POSIX: clear screen + scrollback and return to top-left
        // 2J = clear screen, 3J = clear scrollback, H = cursor home
        echo "\033[2J\033[3J\033[H";
        flush();
    }
}
