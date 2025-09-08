<?php

declare(strict_types=1);

namespace App\Service\Chat;

enum ChatTurnType: string
{
    case User = 'user';
    case Assistant = 'assistant';

    case Tool = 'tool';
}
