<?php

namespace App\Tui\Component;

use PhpTui\Tui\Layout\Constraint;

interface ConstraintAwareComponent
{
    /**
     * Components aware of self constraints and provide it further to layout
     * @return Constraint
     */
    public function constraint(): Constraint;
}
