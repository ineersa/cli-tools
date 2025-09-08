<?php

declare(strict_types=1);

namespace App\Llm;

enum Limits: string
{
    case MaxInputTokens = 'max_input_tokens';
    case MaxOutputTokens = 'max_output_tokens';
    case MaxTokens = 'max_tokens';
}
