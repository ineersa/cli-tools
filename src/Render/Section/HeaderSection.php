<?php

namespace App\Render\Section;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleSectionOutput;

class HeaderSection
{
    private ConsoleSectionOutput $section;
    public function __construct(
        private ConsoleOutput $output,
    ) {
        $this->section = $this->output->section();
    }

    public function clear(): void
    {
        $this->section->clear();
    }

    public function render()
    {
        $data = [
            '',
            '<fg=cyan;options=bold> > AI Client </> <fg=default>CLI prototype</>',
            '<fg=gray>Tips for getting started:</>',
            '<fg=gray>1. Chat, plan tasks, execute tasks.</>',
            '<fg=gray>2. Be specific for the best results.</>',
            '<fg=gray>3. Create a config file to customize behavior.</>',
            '<fg=gray>4. Type </><comment>/help</comment><fg=gray> for available commands.</>',
        ];
        $this->section->overwrite(
            implode(PHP_EOL, $data),
        );
    }
}
