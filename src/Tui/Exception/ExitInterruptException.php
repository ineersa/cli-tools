<?php

declare(strict_types=1);

namespace App\Tui\Exception;

class ExitInterruptException extends \Exception
{
    /**
     * @var string
     */
    protected $message = '/exit received, bye!';
}
