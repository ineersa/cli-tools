<?php

declare(strict_types=1);

namespace App\Events;

use App\Entity\Project;

class ProjectChangedEvent
{
    public function __construct(
        public readonly Project $project,
    ) {
    }
}
