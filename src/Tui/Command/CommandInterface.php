<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Tui\Exception\FollowupException;
use App\Tui\Exception\ProblemException;

interface CommandInterface
{
    public function supports(string $command): bool;

    /**
     * Executes command, returns if followup required
     * For example if /chat executed without params we will require followup
     * /chat list won't require followup.
     *
     * @throws ProblemException
     * @throws FollowupException
     */
    public function execute(string $command): void;
}
