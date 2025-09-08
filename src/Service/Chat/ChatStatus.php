<?php

declare(strict_types=1);

namespace App\Service\Chat;

enum ChatStatus: string
{
    case Open = 'open';

    case Archived = 'archived';
}
