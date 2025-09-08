<?php

declare(strict_types=1);

namespace App\Tests\Tui;

use App\Tui\Command\CopyCommand;
use App\Tui\Command\Runner;
use App\Tui\Component\ContentItemFactory;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CopyCommandTest extends KernelTestCase
{
    public function testSupportsOnlyExactSlashCopy(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        $command = new CopyCommand($state);

        $this->assertTrue($command->supports('/copy'));
        $this->assertTrue($command->supports('   /copy   '), 'Trimmed input should be supported');
        $this->assertFalse($command->supports('/copy now'));
        $this->assertFalse($command->supports('/help'));
    }

    public function testExecuteCompletesWhenCopyableItemExists(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        $state->setContentItems([]);

        // Prepare a stack where the last copyable item is a command card
        $items = [
            ContentItemFactory::make(ContentItemFactory::USER_CARD, 'You said'),
            ContentItemFactory::make(ContentItemFactory::RESPONSE_CARD, 'First response'),
            ContentItemFactory::make(ContentItemFactory::USER_CARD, 'Then you said'),
            ContentItemFactory::make(ContentItemFactory::COMMAND_CARD, 'echo last'),
        ];
        $state->setContentItems($items);

        $copy = new CopyCommand($state);
        $runner = new Runner([$copy]);

        try {
            $runner->runCommand('/copy');
        } catch (CompleteException $exception) {
            $this->assertStringContainsString('/copy', $exception->getMessage());
        }
    }

    public function testExecuteThrowsProblemWhenNoCopyableItemFound(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);
        $state->setContentItems([]);

        $items = [
            ContentItemFactory::make(ContentItemFactory::USER_CARD, 'Only user items'),
            ContentItemFactory::make(ContentItemFactory::EMPTY_ITEM, ''),
        ];
        $state->setContentItems($items);

        $copy = new CopyCommand($state);

        $this->expectException(ProblemException::class);
        $this->expectExceptionMessage('No response found to copy.');
        $copy->execute('/copy');
    }
}
