<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;

class TextContentComponentItems
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
            'logo',
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
            'tips',
            text: Text::fromString($tips),
            style: Style::default()
        );
    }

    public static function getHelp(): ContentItem
    {
        $help = <<<HELP
*MODES*
You can switch between different modes to achieve different behavior of an agent
Modes available:
    - chat -> to chat and ask questions
    - plan -> to plan features or tasks
    - execution -> to execute tasks or plans

*PROJECT*
To organize chats and run actions/commands on some project/codebase
you need to create project first via `/project` command
You can set some options like workdir, is_default and relative path to instructions file

*CHAT*
Chats are saved automatically.
You can manage chat via /chat command.
You can utilize compact function to save context size and restore work from snapshots.

*MODELS*
Agent utilizes both small and large model for different tasks.
Please don't forget to set those up.

*COMMANDS*
Please type `/` inside input box and check available commands.
HELP;
        return new ContentItem(
            'help',
            text: Text::fromString($help),
            style: Style::default(),
            hasBorders: true,
            borderColorHex: '#88E788',
        );
    }
}
