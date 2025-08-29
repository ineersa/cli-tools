<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Text;

class ContentItem
{
    public int $height = 0;

    public function __construct(
        public readonly string $type,
        public readonly Text $text,
        public readonly Style $style,
        public readonly bool $hasBorders = false,
        public readonly string $borderColorHex = '#90FCCF',
        public readonly ?string $originalString = null,
        public readonly ?string $title = null,
        public readonly ?Style $titleStyle = null,
    ) {
    }
}
