<?php

namespace App\Render\Section;

use App\Render\Utils;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

class HistorySection
{
    /** @var list<array{role:string, text:string, variant:string}> */
    private array $history = [];

    private ConsoleSectionOutput $section;

    public function __construct(
        private ConsoleOutput $output,
    ) {
        $this->section = $this->output->section();
        $termH = max(10, (new Terminal())->getHeight() ?? 24);
        $viewport = max(1, $termH - 15); // rows for history

        $this->section->setMaxHeight($viewport); // turn on built‑in scrolling
    }

    public function push(string $text, string $variant): void
    {
        $this->history[] = [
            'data' => [
                'text' => $text,
                'variant' => $variant,
            ],
            'rendered' => $this->renderHistoryItem($text, $variant),
        ];

        // Rendering only last 10 items
        // TODO we are saving everything in array, so user potentially can save chat later, or to cycle back on commands
        // even tho it's a bad idea to save everything in memory
        $toRender = [];
        foreach (array_slice($this->history, -10) as $history) {
            $toRender[] = $history['rendered'];
            $toRender[] = '';
        }

        $this->section->overwrite(implode(PHP_EOL, $toRender));
    }

    public function clear()
    {
        $this->history = [];
        $this->section->clear();
    }

    private function renderHistoryItem(string $text, string $variant): string
    {
        if ($variant === 'assistant') {
            $indent = str_repeat(' ', 8);
            $lines = explode("\n", $text);
            foreach ($lines as $i => $l) {
                $lines[$i] = $i === 0
                    ? $indent . '<fg=yellow>*</> ' . $l
                    : $indent . '  ' . $l;
            }
            return implode(PHP_EOL, $lines);
        }
        $color = $variant === 'command' ? 'green' : 'gray';

        return $this->renderContentWidthBox($text, $color);
    }

    private function renderContentWidthBox(string $text, string $color): string
    {
        $termWidth = max(20, (new Terminal())->getWidth() ?? 100);
        $maxInner  = $termWidth - 2;
        $pad       = 2;

        $lines = explode("\n", $text);
        $longest = 0;
        foreach ($lines as $l) {
            $longest = max($longest, Utils::vlen($l));
        }

        $inner = min($maxInner, max(4, $longest + $pad * 2));
        $body  = [];
        $body[] = "<fg={$color}>╭" . str_repeat('─', $inner) . "╮</>";
        foreach ($lines as $l) {
            $visible = Utils::vlen($l);
            if ($visible > ($inner - $pad * 2)) {
                $l = Utils::vsubstr($l, 0, $inner - $pad * 2);
                $visible = Utils::vlen($l);
            }
            $padding = max(0, $inner - $visible - $pad * 2);
            $body[] = "<fg={$color}>│</>" . str_repeat(' ', $pad) . $l . str_repeat(' ', $pad + $padding) . "<fg={$color}>│</>";
        }
        $body[] = "<fg={$color}>╰" . str_repeat('─', $inner) . "╯</>";

        return implode(PHP_EOL, $body);
    }
}
