<?php

namespace App\Tui\DTO;

use PhpTui\Tui\Style\Style;

class StepComponentDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $question,
        public readonly Style $borderStyle,
        public readonly ?string $hint = null,
        public readonly ?string $progress = null,
    ) {

    }
}
