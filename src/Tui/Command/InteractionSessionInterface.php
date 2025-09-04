<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\FollowupException;

interface InteractionSessionInterface
{
    /**
     * Consume one submitted line.
     * Return a commit payload (array/DTO) when done.
     * Throw FollowupException to ask the next question.
     * Throw ProblemException to show an error and stay on the same step.
     */
    public function step(string $line): void;

    /**
     * @throws FollowupException
     */
    public function sendInitialMessage(): never;

    /** Called by UI to abort (Esc/Ctrl+C). */
    public function cancel(): void;
}
