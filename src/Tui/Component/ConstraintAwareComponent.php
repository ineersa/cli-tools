<?php

declare(strict_types=1);

namespace App\Tui\Component;

use PhpTui\Tui\Layout\Constraint;

interface ConstraintAwareComponent
{
    /**
     * Components aware of self constraints and provide it further to layout.
     */
    public function constraint(): Constraint;
}
