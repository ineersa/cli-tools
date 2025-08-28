<?php

declare(strict_types=1);

namespace App\Tui\Component;

use App\Tui\State;
use PhpTui\Term\Event;
use PhpTui\Tui\Color\AnsiColor;
use PhpTui\Tui\Color\RgbColor;
use PhpTui\Tui\Extension\Core\Widget\Block\Padding;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\CompositeWidget;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\List\ListItem;
use PhpTui\Tui\Extension\Core\Widget\List\ListState;
use PhpTui\Tui\Extension\Core\Widget\ListWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Span;
use PhpTui\Tui\Text\Text;
use PhpTui\Tui\Widget\Borders;
use PhpTui\Tui\Widget\Direction;
use PhpTui\Tui\Widget\Widget;

class ContentItem
{
    public int $height = 0;

    public function __construct(
        public readonly Text $text,
        public readonly Style $style,
        public readonly bool $hasBorders = false,
        public readonly string $borderColorHex = '#90FCCF',
        public readonly ?string $originalString = null,
        public readonly ?string $title = null,
        public readonly ?Style $titleStyle = null,
    ) {}

}
