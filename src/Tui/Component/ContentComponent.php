<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\CompositeWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\List\ListState;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Widget;

class ContentComponent implements Component
{

    public function __construct(
        private State $state,
    ) {}

    public function build(): Widget
    {
        $header = ParagraphWidget::fromString(<<<TXT
        My awesome app header:
        - some description
        - do something
        TXT);
        $user = ParagraphWidget::fromString('User query text here: Lorem ipsum dolor sit amet');
        $response = ParagraphWidget::fromString('* Response query text here: This text should support streamable input');
        $action = ParagraphWidget::fromString('○ Some action progress indicator here…');

        return BlockWidget::default()
            ->borders(Borders::NONE)
            ->padding(Padding::all(1))
            ->widget(
                new CompositeWidget(
                    [
                        $header,
                        $user,
                        $response,
                        $action,
                    ]
                )
            )
        ;
    }

    public function handle(Event $event): void
    {
        // TODO: Implement handle() method.
    }
}
