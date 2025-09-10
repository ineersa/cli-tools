<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Agent\Agent;
use App\Entity\Project;
use App\EventListener\ProjectChangedListener;
use App\Events\ProjectChangedEvent;
use PHPUnit\Framework\TestCase;

final class ProjectChangedListenerTest extends TestCase
{
    public function testOnProjectChangedEventSetsProjectOnAgent(): void
    {
        $agent = $this->createMock(Agent::class);
        $listener = new ProjectChangedListener($agent);

        $project = (new Project())->setId(1)->setName('Test')->setWorkdir('/tmp')->setIsDefault(true)->setInstructions('i');

        $agent->expects($this->once())
            ->method('setProject')
            ->with($project);

        $listener->onProjectChangedEvent(new ProjectChangedEvent($project));
    }
}
