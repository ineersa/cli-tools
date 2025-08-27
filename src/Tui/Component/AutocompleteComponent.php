<?php

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Title;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

class AutocompleteComponent implements Component
{

    public function __construct(
        private State $state,
    ) {}

    public function build(): Widget
    {
        return BlockWidget::default()
            ->borders(Borders::NONE)
            ->titles(Title::fromString('Commands'))
            ->padding(Padding::horizontal(1))
            ->widget(
                ListWidget::default()->items(
                    ListItem::fromString('↑  /help  Description 1'),
                    ListItem::fromString('   /clear Description 2'),
                    ListItem::fromString('   /chat  Chat 1'),
                    ListItem::fromString('↓  /exit  Quit'),
                    ListItem::fromString('4 out of 26'),
                )->highlightStyle(Style::default()->bg(AnsiColor::LightYellow))
            );
    }

    public function handle(Event $event): void
    {
        // TODO: Implement handle() method.
    }
}
