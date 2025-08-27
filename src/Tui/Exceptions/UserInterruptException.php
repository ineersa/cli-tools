<?php

declare(strict_types=1);

namespace App\Tui\Exceptions;

class UserInterruptException extends \Exception
{
    protected $message = 'Interrupt key received, bye!';
}
