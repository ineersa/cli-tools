<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Agent\Agent;
use App\Agent\Mode;
use App\EventListener\ModeChangeListener;
use App\Events\ModeChangedEvent;
use PHPUnit\Framework\TestCase;

final class ModeChangeListenerTest extends TestCase
{
    public function testOnModeChangedEventSetsModeOnAgent(): void
    {
        $agent = $this->createMock(Agent::class);
        $listener = new ModeChangeListener($agent);

        $agent->expects($this->once())
            ->method('setMode')
            ->with(Mode::Plan);

        $listener->onModeChangedEvent(new ModeChangedEvent(Mode::Plan));
    }
}
