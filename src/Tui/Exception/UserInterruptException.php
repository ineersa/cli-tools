<?php

declare(strict_types=1);

namespace App\Tui\Exception;

class UserInterruptException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'Interrupt key received, bye!';
}
