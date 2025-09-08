<?php

declare(strict_types=1);

namespace App\Worker;

use App\Agent\Agent;
use App\Message\AssistantResponseReceived;
use App\Tui\Component\ContentItemFactory;
use App\Tui\Component\ProgressComponent;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class QuestionHandlerWorker implements WorkerInterface
{
    private Process $process;
    private string $buffer = '';
    private string $responseBuffer = '';
    private int $contentItemIdx = -1;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private LoggerInterface $logger,
        private State $state,
        private Agent $agent,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function start(string $requestId, string $question): void
    {
        if (isset($this->process) && $this->process->isRunning()) {
            throw new ProblemException('Question handling process is already running.');
        }
        // TODO this should be DTO and serialized
        $payload = json_encode([
            'type' => 'StartQuestion',
            'requestId' => $requestId,
            'chatId' => $this->agent->getActiveChat()?->getId(),
            'question' => $question,
        ], \JSON_UNESCAPED_UNICODE);

        $this->contentItemIdx = -1;
        $this->responseBuffer = '';
        $this->process = new Process([\PHP_BINARY, $this->projectDir.'/bin/console', 'app:question-handler']);
        $this->process->setTimeout(null);
        $input = new InputStream();
        $input->write($payload."\n");
        $input->close();
        $this->process->setInput($input);
        $this->process->start();
        $this->agent->attachWorker($requestId, $this);
    }

    public function poll(string $requestId): void
    {
        foreach ($this->pump() as $msg) {
            switch ($msg['type'] ?? '') {
                case 'StreamDelta':
                    $this->responseBuffer .= $msg['delta'];

                    $this->state->setRequireReDrawing(true);
                    break;
                case 'Progress':
                    $this->state->setDynamicIslandComponents([
                        ProgressComponent::NAME => new ProgressComponent(
                            $msg['phase'] ?? 'unknown', $this->state
                        ),
                    ]);
                    $this->state->setRequireReDrawing(true);
                    break;
                case 'Done':
                    $message = 'Message: '.$msg['finishReason']."\n";
                    if ($msg['usage']) {
                        $message .= ' Usage: '.json_encode($msg['usage'])."\n";
                    }
                    $this->state->setDynamicIslandComponents([
                        ProgressComponent::NAME => new ProgressComponent($message, $this->state),
                    ]);
                    $this->agent->detachWorker($requestId);
                    $message = new AssistantResponseReceived(
                        projectId: (int) $this->agent->getProject()?->getId(),
                        requestId: $requestId,
                        response: $this->responseBuffer,
                        mode: $this->agent->getMode(),
                        chatId: $this->agent
                            ->setActiveChat()
                            ->getActiveChat()?->getId(),
                        finishReason: $msg['finishReason'] ?? 'stop',
                        promptTokens: $msg['usage']['promptTokens'] ?? null,
                        completionTokens: $msg['usage']['completionTokens'] ?? null,
                        totalTokens: $msg['usage']['totalTokens'] ?? null,
                    );
                    $this->messageBus->dispatch($message);
                    break;
                case 'Error':
                    $this->agent->detachWorker($requestId);
                    throw new ProblemException($msg['message'] ?? 'Unknown');
            }
        }

        $item = ContentItemFactory::make(ContentItemFactory::RESPONSE_CARD, $this->responseBuffer);
        $item->height = 0;
        if (-1 === $this->contentItemIdx) {
            $this->contentItemIdx = $this->state->pushContentItem($item);
        } else {
            $this->state->pushContentItem($item, $this->contentItemIdx);
        }
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->signal(\SIGTERM);
            $this->process->wait();
            throw new ProblemException('Process terminated.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pump(): array
    {
        $out = $this->process->getIncrementalOutput();
        $err = $this->process->getIncrementalErrorOutput();

        if ('' !== $err) {
            $this->logger->error('Question handler worker error!', [
                'error' => $err,
            ]);
            throw new ProblemException($err);
        }
        if ('' !== $out) {
            $this->buffer .= $out;
        }

        $messages = [];
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);
            if ('' === $line) {
                continue;
            }
            $msg = json_decode($line, true);
            if (\is_array($msg)) {
                $messages[] = $msg;
            }
        }

        return $messages;
    }
}
