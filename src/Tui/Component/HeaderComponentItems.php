<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;

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
