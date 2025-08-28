<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class ContentItemFactory
{
    public const EMPTY_ITEM = 'empty-item';
    public const USER_CARD = 'user-card';

    public const RESPONSE_CARD = 'response-card';

    public const COMMAND_CARD = 'command-card';

    public static function make(string $type, string $input = ''): ContentItem
    {
        return match ($type) {
            self::EMPTY_ITEM => new ContentItem(Text::fromLines(Line::fromString('')), Style::default()),
            self::USER_CARD => new ContentItem(
                text: Text::fromString($input),
                style: Style::default()->fg(AnsiColor::DarkGray),
                hasBorders: true,
                borderColorHex: '#90FCCF',
                title: 'You',
                titleStyle: Style::default()->fg(RgbColor::fromHex('#90FCCF')),
            ),
            self::RESPONSE_CARD => new ContentItem(
                text: Text::fromString($input),
                style: Style::default(),
                hasBorders: true,
                borderColorHex: '#FFDE21',
                originalString: $input,
                title: 'Response',
                titleStyle: Style::default()->fg(RgbColor::fromHex('#FFDE21')),
            ),
            self::COMMAND_CARD => new ContentItem(
                text: Text::fromString($input),
                style: Style::default()->fg(AnsiColor::Green),
                hasBorders: true,
                borderColorHex: '#88E788'
            ),
            default => throw new \LogicException('Not implemented card type'),
        };
    }
}
