<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Widget;

class HelpStringComponent implements Component
{
    public const HEIGHT = 1;

    private const HELP_STRING = 'Enter = newline/accept · Ctrl+D = submit · Arrows ←→↑↓ · Ctrl+C quits · Ctrl+Y = delete line · Esc+Esc = clear · PgUp/PgDown = scroll content';

    public function build(): Widget
    {
        return ParagraphWidget::fromText(
            Text::fromString(self::HELP_STRING),
        )
            ->style(Style::default()->fg(AnsiColor::DarkGray));
    }

    public function handle(Event $event): void
    {
        // TODO: Implement handle() method.
    }
}
