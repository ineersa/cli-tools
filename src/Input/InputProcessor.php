<?php

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
        $this->stdin = fopen('php://stdin', 'rb');
        if (!$this->stdin) {
            throw new \RuntimeException("Unable to open stdin");
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
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
        }
    }

    /**
     * TODO
     * @return string
     */
    public function readKey(): string
    {
        $c = fgetc($this->stdin);
        if ($c === false) return 'NONE';

        $ord = ord($c);

        if ($c === "\r" || $c === "\n") return 'ENTER';

        if ($ord === 4)  return 'CTRL_D';
        if ($ord === 3)  return 'CTRL_C';
        if ($ord === 14) return 'CTRL_N';

        if ($ord === 127 || $ord === 8) return 'BACKSPACE';

        if ($c === "\033") {
            $n1 = fgetc($this->stdin);
            $n2 = fgetc($this->stdin);
            if ($n1 === '[') {
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

        if ($ord === 9) return 'TAB';

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
