<?php

namespace App\Tui\Component;

use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class HeaderComponentItems
{

    public static function getLogo(): ContentItem
    {
        $text = <<<TXT

██╗ ██╗      ██╗  ██╗ █████╗ ████████╗███████╗██╗███████╗██╗     ██████╗     ██╗██╗██╗
╚██╗╚██╗     ██║  ██║██╔══██╗╚══██╔══╝██╔════╝██║██╔════╝██║     ██╔══██╗    ██║██║██║
 ╚██╗╚██╗    ███████║███████║   ██║   █████╗  ██║█████╗  ██║     ██║  ██║    ██║██║██║
 ██╔╝██╔╝    ██╔══██║██╔══██║   ██║   ██╔══╝  ██║██╔══╝  ██║     ██║  ██║    ╚═╝╚═╝╚═╝
██╔╝██╔╝     ██║  ██║██║  ██║   ██║   ██║     ██║███████╗███████╗██████╔╝    ██╗██╗██╗
╚═╝ ╚═╝      ╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝   ╚═╝     ╚═╝╚══════╝╚══════╝╚═════╝     ╚═╝╚═╝╚═╝

TXT;

        return new ContentItem(
            text: Text::fromString($text),
            style: Style::default()->fg(RgbColor::fromHex('#305CDE'))
        );
    }

    public static function getTips(): ContentItem
    {
        $tips = <<<TIPS
  Tips for getting started:
  1. Use modes:
    - chat -> to chat and ask questions
    - plan -> to plan features or tasks
    - execution -> to execute tasks or plans
  2. You can reference files/classes with @filename, will use fuzzy search
  3. /help for more information.
  4. Enjoy!
TIPS;
        return new ContentItem(
            text: Text::fromString($tips),
            style: Style::default()
        );
    }
}
