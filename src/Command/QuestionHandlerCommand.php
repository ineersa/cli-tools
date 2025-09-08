<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\Agent;
use App\Entity\Chat;
use App\Llm\Limits;
use App\Service\ChatService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:question-handler')]
final class QuestionHandlerCommand extends Command
{
    /**
     * @var array{promptTokens:int, completionTokens:int, totalTokens:int}|null
     */
    private ?array $finalUsage = null;

    private mixed $tool = null;
    private ?Chat $chat;
    private bool $terminate = false;

    public function __construct(
        private Agent $agent,
        private LoggerInterface $logger,
        private readonly ChatService $chatService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (\function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(\SIGTERM, function () {
                $this->terminate = true;
            });
        }

        $line = fgets(\STDIN);
        if (false === $line) {
            $this->emit($output, ['type' => 'Error', 'code' => 'EMPTY_INPUT', 'message' => 'No payload']);

            return Command::FAILURE;
        }
        $msg = json_decode(trim($line), true);
        if (!\is_array($msg) || ($msg['type'] ?? '') !== 'StartQuestion') {
            $this->emit($output, ['type' => 'Error', 'code' => 'BAD_INPUT', 'message' => 'Invalid payload']);

            return Command::FAILURE;
        }

        $rid = (string) $msg['requestId'];
        $question = (string) $msg['question'];
        $chatId = $msg['chatId'] ?? null;
        $this->chat = $chatId ? $this->chatService->chatRepository->find($chatId) : null;

        try {
            $this->emit($output, ['type' => 'Ack', 'requestId' => $rid]);

            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'Collect context']);
            $context = $this->collectContext($question);
            if ($this->checkTerminate()) {
                return Command::SUCCESS;
            }

            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'Loading history']);
            $history = ['messages' => [], 'summary' => null, 'turns' => []];
            if ($this->chat) {
                $history = $this->chatService->loadHistory($this->chat, $this->agent->largeModel->getLimit(Limits::MaxInputTokens));
            }

            if ($this->checkTerminate()) {
                return Command::SUCCESS;
            }

            $params = $this->bundle($context, $history);
            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'Sending request to LLM']);

            $this->streamOpenAI($params, function (string $delta) use ($output, $rid) {
                $this->emit($output, ['type' => 'StreamDelta', 'requestId' => $rid, 'delta' => $delta]);
            });

            $this->emit($output, ['type' => 'Citations', 'requestId' => $rid, 'items' => $context['citations']]);
            $this->emit($output, [
                'type' => 'Done', 'requestId' => $rid, 'finishReason' => 'done',
                'usage' => $this->finalUsage, 'tool' => $this->tool,
            ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['trace' => $e->getTrace()]);

            $this->emit($output, ['type' => 'Error', 'requestId' => $rid, 'code' => $e->getCode(), 'message' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    private function checkTerminate(): bool
    {
        return true === $this->terminate;
    }

    /**
     * @param array<string, mixed> $msg
     */
    private function emit(OutputInterface $out, array $msg): void
    {
        $line = json_encode($msg, \JSON_UNESCAPED_UNICODE)."\n";
        fwrite(\STDOUT, $line);
        fflush(\STDOUT);
    }

    /**
     * @return array{citations: list<mixed>, question: string}
     */
    private function collectContext(string $question): array
    {
        // TODO: enrich with mode/project metadata and future citations
        return [
            'citations' => [],
            'question' => $question,
        ];
    }

    /**
     * @param array{citations: list<mixed>, question: string}                                                                                  $context
     * @param array{messages: list<array{role: 'assistant'|'user', content: string}>, summary: string|null, turns: list<\App\Entity\ChatTurn>} $history
     *
     * @return array{messages: list<array{role: 'assistant'|'user'|'system', content: string}>}
     */
    private function bundle(array $context, array $history): array
    {
        $systemPrompt = $this->buildSystemPrompt();

        /** @var list<array{role: 'assistant'|'user'|'system', content: string}> $messages */
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        foreach ($history['messages'] as $m) {
            $messages[] = $m;
        }

        if ($history['summary'] ?? null) {
            $messages[] = [
                'role' => 'system',
                'content' => \sprintf('Conversation summary: %s', $history['summary']),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $context['question']];

        return [
            'messages' => $messages,
            // 'tools' => [], // TODO: future tool schemas
            // 'tool_choice' => 'auto',
        ];
    }

    /**
     * @param array{messages: list<array{role: 'assistant'|'user'|'system', content: string}>} $params
     */
    private function streamOpenAI(array $params, callable $onDelta): void
    {
        foreach ($this->agent->largeModel->completionStreamed($params) as $event) {
            $delta = $event->choices[0]->delta->content ?? '';
            if ('' !== $delta) {
                $onDelta($delta);
            }
            $tc = $event->choices[0]->delta->toolCalls[0] ?? null;
            if ($tc) {
                $this->tool = $tc; // TODO: future: dispatch tool execution
            }
            if (null !== $event->usage) {
                $this->finalUsage = [
                    'promptTokens' => $event->usage->promptTokens,
                    'completionTokens' => $event->usage->completionTokens,
                    'totalTokens' => $event->usage->totalTokens,
                ];
            }
            if ($this->checkTerminate()) {
                return;
            }
        }
    }

    private function buildSystemPrompt(): string
    {
        // Minimal guardrail; TODO: mode-aware and project-aware instructions
        return 'You are a helpful assistant for a CLI tools project.
        Be concise and accurate.
        If context is insufficient, ask clarifying questions.';
    }
}
