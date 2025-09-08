<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\Message\QuestionReceivedMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class QuestionReceivedMessageTest extends KernelTestCase
{
    public function testMessageCanBeDispatched(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $bus = $container->get(MessageBusInterface::class);

        $bus->dispatch(new QuestionReceivedMessage('req_1', 'What is up?', 1));
    }
}
