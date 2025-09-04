<?php

namespace App\Service\Chat;

enum ChatTurnType: string
{
    case User = 'user';
    case Assistant = 'assistant';

    case Tool = 'tool';
}
