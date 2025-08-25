<?php

namespace App\Render\Section;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class AutoCompleteSection
{
    /** @var list<array{name:string, desc:string}> */
    private array $commands = [
        ['name' => '/help',    'desc' => 'show help and available commands'],
        ['name' => '/chat',    'desc' => 'manage conversation history'],
        ['name' => '/clear',   'desc' => 'clear the screen and history, clears chat too'],
        ['name' => '/copy',    'desc' => 'copy the last result to clipboard'],
        ['name' => '/mode',    'desc' => 'switch to mode: *chat*, *planning*, *execution*'],
        ['name' => '/exit',    'desc' => 'quit'],
    ];

    private int $linesAc = 0;

    private int $acMaxRows = 4;

    private ConsoleSectionOutput $section;
    private array $acFiltered = [];
    /**
     * @var int|mixed
     */
    private mixed $acCursor = 0;
    private bool $acVisible = false;

    public function __construct(
        private ConsoleOutput $output,
    ) {
        $this->section = $this->output->section();
    }

    public function clear(): void
    {
        $this->section->clear();
    }

    /**
     * @param string $buffer
     * @return bool Whether AC widget requires re-rendering
     */
    public function updateState(string $buffer): bool
    {
        if ($buffer !== '' && $buffer[0] === '/' && !preg_match('/\s/', $buffer)) {
            $q = mb_strtolower($buffer);
            $acFiltered = array_values(array_filter(
                $this->commands,
                static fn(array $c): bool =>
                    str_starts_with(mb_strtolower($c['name']), $q)
                    || str_contains(mb_strtolower($c['desc']), ltrim($q, '/'))
            ));

            $acVisible = $acFiltered !== [];
            $acCursor  = min($this->acCursor, max(0, count($acFiltered) - 1));

            $stateChanged = $acFiltered !== $this->acFiltered
                || $acVisible !== $this->acVisible
                || $acCursor  !== $this->acCursor;

            $this->acFiltered = $acFiltered;
            $this->acCursor   = $acCursor;
            $this->acVisible  = $acVisible;
        } else {
            $stateChanged = $this->acVisible !== false
                || $this->acFiltered !== []
                || $this->acCursor !== 0;

            $this->acVisible  = false;
            $this->acFiltered = [];
            $this->acCursor   = 0;
            $this->linesAc = 1;
        }

        return $stateChanged;
    }

    public function render(): void
    {
        if (!$this->acVisible || $this->acFiltered === []) {
            $this->linesAc = 1;
            $this->section->overwrite('');
            return;
        }

        $total = count($this->acFiltered);
        $start = intdiv($this->acCursor, $this->acMaxRows) * $this->acMaxRows;
        $end   = min($total, $start + $this->acMaxRows);

        $rows = [];
        for ($i = $start; $i < $end; $i++) {
            $item  = $this->acFiltered[$i];
            $isSel = ($i === $this->acCursor);
            $name  = $isSel ? "<options=reverse>{$item['name']}</>" : $item['name'];
            $rows[] = sprintf('%-16s  <fg=gray>%s</>', $name, $item['desc']);
        }

        if ($total > $this->acMaxRows) {
            $page  = intdiv($this->acCursor, $this->acMaxRows) + 1;
            $pages = (int)ceil($total / $this->acMaxRows);
            $rows[] = "<fg=gray>({$page}/{$pages})</>";
        }

        $this->linesAc = count($rows);
        $this->section->overwrite(implode(PHP_EOL, $rows));
    }

    public function getLinesAc(): int
    {
        return $this->linesAc;
    }
}
