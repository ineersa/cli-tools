<?php

declare(strict_types=1);

namespace App\Tests\Tui;

use App\Tui\Command\HelpCommand;
use App\Tui\Command\Runner;
use App\Tui\Component\ContentItem;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HelpCommandTest extends KernelTestCase
{
    public function testHelpCommandPushesHelpItemAndCompletes(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var State $state */
        $state = $container->get(State::class);

        $help = new HelpCommand($state);
        $runner = new Runner([$help]);

        try {
            $runner->runCommand('/help');
        } catch (CompleteException $exception) {
            $this->assertStringContainsString('/help', $exception->getMessage());
        }

        $items = $state->getContentItems();
        $this->assertNotEmpty($items, 'HelpCommand should push a content item');
        $lastItem = $items[array_key_last($items)];
        $this->assertInstanceOf(ContentItem::class, $lastItem);
        $this->assertSame('help', $lastItem->type);
        $this->assertGreaterThan(0, $lastItem->text->height(), 'Help item should contain some lines of text');
    }
}
