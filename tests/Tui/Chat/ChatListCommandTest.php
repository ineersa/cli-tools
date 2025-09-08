<?php

declare(strict_types=1);

namespace App\Tests\Tui\Chat;

use App\Tui\Command\Chat\ChatListCommand;
use App\Tui\Component\TableListComponent;
use App\Tui\Exception\CompleteException;
use App\Tui\State;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChatListCommandTest extends KernelTestCase
{
    public function testListDisplaysTableAndCompletesWithCount(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        // Fixtures are loaded once at the beginning by Composer test script.

        // Fetch the project seeded by fixtures
        $project = $em->getRepository(\App\Entity\Project::class)->findOneBy(['name' => 'demo']);
        $this->assertNotNull($project, 'Project seeded by fixtures should exist');

        // Use real State from the container and set current project
        /** @var State $state */
        $state = $container->get(State::class);
        $state->setProject($project);

        // Precondition: dynamic island is empty and editing flag can be toggled by command
        $state->setDynamicIslandComponents([]);
        $state->setEditing(true);

        // Use real ChatService from container (wired to real repository)
        $chatService = $container->get(\App\Service\ChatService::class);

        $command = new ChatListCommand($chatService, $state);

        try {
            $command->list();
        } catch (CompleteException $exception) {
            $this->assertStringContainsString('/chat list', $exception->getMessage());
            $this->assertStringContainsString('Found 2 chats', $exception->getMessage());
        }

        // Verify state updated by command
        $components = $state->getDynamicIslandComponents();
        $this->assertArrayHasKey(TableListComponent::NAME, $components);
        $this->assertInstanceOf(TableListComponent::class, $components[TableListComponent::NAME]);
        $this->assertFalse($state->isEditing());
    }
}
