<?php

declare(strict_types=1);

namespace App\Tests\Tui;

use App\Agent\Agent;
use App\Service\ChatService;
use App\Tui\Command\ClearCommand;
use App\Tui\Command\Runner;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ClearCommandTest extends KernelTestCase
{
    public function testSupportsVariants(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $state = $container->get(State::class);
        $chatService = $container->get(ChatService::class);
        $agent = $container->get(Agent::class);

        $command = new ClearCommand($state, $chatService, $agent);

        $this->assertTrue($command->supports('/clear'));
        $this->assertTrue($command->supports('  /clear  '));
        $this->assertFalse($command->supports('/clear-now'));
        $this->assertFalse($command->supports('clear'));
    }

    public function testExecuteWithoutActiveChatResetsViewAndCompletes(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $state = $container->get(State::class);
        $chatService = $container->get(ChatService::class);
        $agent = $container->get(Agent::class);

        // Ensure no active chat
        $agent->cleanUpChat();
        $this->assertNull($agent->getActiveChat());

        $command = new ClearCommand($state, $chatService, $agent);

        try {
            // Execute through Runner to mimic real flow
            (new Runner([$command]))->runCommand('/clear');
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/clear', $e->getMessage());
            $this->assertStringContainsString('Chat reset', $e->getMessage());
        }

        $items = $state->getContentItems();
        $this->assertNotEmpty($items);
        $this->assertSame('logo', $items[0]->type);
    }

    public function testExecuteWithActiveChatResetsChatAndCompletes(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $state = $container->get(State::class);
        $chatService = $container->get(ChatService::class);
        $agent = $container->get(Agent::class);

        // Ensure there is an active chat: force open chat via ChatService and Agent
        $agent->setActiveChat();
        $this->assertNotNull($agent->getActiveChat(), 'Agent should have an active chat before clearing');

        $command = new ClearCommand($state, $chatService, $agent);

        try {
            (new Runner([$command]))->runCommand('/clear');
        } catch (CompleteException $e) {
            $this->assertStringContainsString('/clear', $e->getMessage());
            $this->assertStringContainsString('Chat reset', $e->getMessage());
        }

        // After reset, state content should be logo and a new active chat may be set
        $items = $state->getContentItems();
        $this->assertNotEmpty($items);
        $this->assertSame('logo', $items[0]->type);
    }
}
