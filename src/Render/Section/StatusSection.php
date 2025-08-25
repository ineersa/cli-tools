<?php

namespace App\Render\Section;

use App\Render\Utils;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class StatusSection
{
    private ConsoleSectionOutput $section;
    private string $mode;
    private string $model;

    public function __construct(
        private ConsoleOutput $output,
    ) {
        $this->section = $this->output->section();
    }

    public function render(): void
    {
        $cwd = getcwd() ?: '~';
        $left   = "<fg=cyan>{$cwd}</>";
        $center = "<fg=red>{$this->mode}</>";
        $right  = "<fg=magenta>{$this->model}</>";

        $termWidth = (new Terminal())->getWidth() ?? 100;
        $leftStr   = Utils::vstrip($left);
        $centerStr = Utils::vstrip($center);
        $rightStr  = Utils::vstrip($right);

        $spaceForCenter = max(1, $termWidth - strlen($leftStr) - strlen($rightStr) - 4);
        $centerPadded = str_pad($centerStr, $spaceForCenter, ' ', STR_PAD_BOTH);

        $this->section->overwrite($left . ' ' . $centerPadded . ' ' . $right);
    }

    public function setMode(string $mode): StatusSection
    {
        $this->mode = $mode;
        return $this;
    }

    public function setModel(string $model): StatusSection
    {
        $this->model = $model;
        return $this;
    }

    public function clear()
    {
        $this->section->clear();
    }
}
