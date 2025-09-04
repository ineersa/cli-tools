<?php

namespace App\Service\Chat;

enum ChatStatus: string
{
    case Open = 'open';

    case Archived = 'archived';
}
