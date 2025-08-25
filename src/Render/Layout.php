<?php

namespace App\Render;

use App\Render\Section\AutoCompleteSection;
use App\Render\Section\HeaderSection;
use App\Render\Section\HistorySection;
use App\Render\Section\InputBoxSection;
use App\Render\Section\StatusSection;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Terminal;

class Layout
{
    private array $sections = [];

    public function __construct(
        private ConsoleOutput   $output,
        private RenderProcessor $processor,
    )
    {
    }

    public function init(): void
    {
        $this->sections[HeaderSection::class]       = new HeaderSection($this->output);
        $this->sections[HistorySection::class]      = new HistorySection($this->output);
        $this->sections[InputBoxSection::class]     = new InputBoxSection($this->output);
        $this->sections[AutoCompleteSection::class] = new AutoCompleteSection($this->output);
        $this->sections[StatusSection::class]       = new StatusSection($this->output);

        // Render header/history first (top of the screen)
        $this->sections[HeaderSection::class]->render();
        $this->sections[HistorySection::class]->clear();

        // Anchor to the bottom BEFORE printing the input/AC/status stack
        $rows = ((new Terminal())->getHeight() ?? 999) - 1;
        echo "\033[{$rows};1H";        // CUP: absolute row=bottom, col=1

        // Input content
        $this->sections[InputBoxSection::class]->render('');
        $this->sections[AutoCompleteSection::class]->render();
        // AC starts empty (0 lines). Its render() will overwrite('') when hidden.
        // (No need to render AC now; it will be rendered on first processInputBox())

        // Status LAST so the real cursor ends at bottom
        $this->sections[StatusSection::class]
            ->setMode('chat')
            ->setModel('GPT-OSS(20b local)')
            ->render();

        // Place caret at input
        $lift = 3;
        $col  = $this->sections[InputBoxSection::class]->getCaretColumn();
        echo "\033[{$lift}A\033[{$col}G";
        flush();
    }

    public function clearSection(string $section): void
    {
        if (!isset($this->sections[$section])) {
            throw new \RuntimeException("Section '$section' does not exist");
        }
        $this->sections[$section]->clear();
    }

    public function getSection(string $section): HeaderSection|HistorySection|InputBoxSection|AutoCompleteSection|StatusSection
    {
        if (!isset($this->sections[$section])) {
            throw new \RuntimeException("Section '$section' does not exist");
        }
        return $this->sections[$section];
    }
}
