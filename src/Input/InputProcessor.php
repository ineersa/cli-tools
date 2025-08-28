<?php

declare(strict_types=1);

namespace App\Input;

class InputProcessor
{
    /**
     * @var resource
     */
    private $stdin;

    private string $inputBuffer = '';

    public function __construct()
    {
        $this->stdin = fopen('php://stdin', 'r');
        if (!$this->stdin) {
            throw new \RuntimeException('Unable to open stdin');
        }
        stream_set_blocking($this->stdin, true);
    }

    public function enterRawMode(): void
    {
        shell_exec('stty -icanon -echo 2>/dev/null');
    }

    public function leaveRawMode(): void
    {
        shell_exec('stty sane 2>/dev/null');
    }

    public function closeStdin(): void
    {
        if (\is_resource($this->stdin)) {
            fclose($this->stdin);
        }
    }

    /**
     * TODO.
     */
    public function readKey(): string
    {
        $c = fgetc($this->stdin);
        if (false === $c) {
            return 'NONE';
        }

        $ord = \ord($c);

        if ("\r" === $c || "\n" === $c) {
            return 'ENTER';
        }

        if (4 === $ord) {
            return 'CTRL_D';
        }
        if (3 === $ord) {
            return 'CTRL_C';
        }
        if (14 === $ord) {
            return 'CTRL_N';
        }

        if (127 === $ord || 8 === $ord) {
            return 'BACKSPACE';
        }

        if ("\033" === $c) {
            $n1 = fgetc($this->stdin);
            $n2 = fgetc($this->stdin);
            if ('[' === $n1) {
                return match ($n2) {
                    'A' => 'UP',
                    'B' => 'DOWN',
                    'C' => 'RIGHT',
                    'D' => 'LEFT',
                    default => 'ESC',
                };
            }

            return 'ESC';
        }

        if (9 === $ord) {
            return 'TAB';
        }

        if ($ord >= 32 && $ord <= 126) {
            $this->inputBuffer .= $c;

            return 'CHAR';
        }

        return 'OTHER';
    }

    public function getInputBuffer(): string
    {
        return $this->inputBuffer;
    }
}
