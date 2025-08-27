<?php

declare(strict_types=1);

namespace App\Agent;

enum Mode: string
{
    case Chat = 'chat';
    case Plan = 'plan';
    case Execution = 'execution';

    public static function getDefaultMode(): Mode
    {
        return self::Chat;
    }

    public function getNextMode(): Mode
    {
        return match ($this) {
            self::Chat => self::Plan,
            self::Plan => self::Execution,
            self::Execution => self::Chat
        };
    }
}
