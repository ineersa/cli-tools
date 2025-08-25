<?php

namespace App\Render\Section;

use App\Render\Utils;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Terminal;

class InputBoxSection
{
    private ConsoleSectionOutput $section;

    private int $caretColumn;

    public function __construct(
        private ConsoleOutput $output,
    ) {
        $this->section = $this->output->section();
    }

    public function render(string $buffer): void
    {
        $text = rtrim($this->renderInputBoxString($buffer));
        $this->section->overwrite($text);
    }

    public function clear(): void
    {
        $this->section->clear();
    }

    /**
     * Full-width input box (purple) as string. Returns [string, caretUp, caretCol].
     *
     * @param string $buffer
     * @return string
     */
    private function renderInputBoxString(string $buffer): string
    {
        $termWidth = max(20, (new Terminal())->getWidth() ?? 100);
        $inner = $termWidth - 2;

        $lines = $buffer === '' ? [''] : explode("\n", $buffer);

        $promptVisible = '> ';
        $contPrefix    = '  ';

        $rendered = [];
        foreach ($lines as $i => $l) {
            $prefix = $i === 0 ? $promptVisible : $contPrefix;
            $content = $l;
            if ($content === '' && $i === 0) {
                $content = '<fg=gray>Type your message or @path/to/file</>';
            }

            $visiblePrefixLen  = Utils::vlen($prefix);
            $maxContent = max(0, $inner - 1 - $visiblePrefixLen - 1);
            if (Utils::vlen($content) > $maxContent) {
                $content = Utils::vsubstr($content, 0, $maxContent);
            }

            $lineVisibleLen = 1 + 1 + $visiblePrefixLen + Utils::vlen($content) + 1;
            $padding = max(0, $inner - ($lineVisibleLen - 1));

            $rendered[] = '<fg=magenta>│</> ' . '<options=bold>' . $prefix . '</>' .
                $content . str_repeat(' ', $padding + 1) . '<fg=magenta>│</>';
        }

        // Caret on the last content line
        $lastLine  = end($lines) ?: '';
        $isFirst   = (count($lines) === 1);
        $prefixLen = Utils::vlen($isFirst ? $promptVisible : $contPrefix);

        // left frame '│' + space = 2; then prefix + content; +1 to sit *after* last char
        $col = 2 + $prefixLen + Utils::vlen($lastLine) + 1;

        // keep caret within the inner frame
        $this->caretColumn = max(1, min($col, $inner + 2));

        return implode(PHP_EOL, $rendered);
    }

    public function getCaretColumn(): int
    {
        return $this->caretColumn;
    }
}
