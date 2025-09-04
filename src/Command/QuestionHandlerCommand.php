<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\Agent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:question-handler')]
final class QuestionHandlerCommand extends Command
{
    public function __construct(
        private Agent $agent,
    ) {
        parent::__construct();
    }
    private bool $terminate = false;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () {
                $this->terminate = true;
            });
        }

        $line = fgets(STDIN);
        if ($line === false) {
            $this->emit($output, ['type' => 'Error', 'code' => 'EMPTY_INPUT', 'message' => 'No payload']);
            return Command::FAILURE;
        }
        $msg = json_decode(trim($line), true);
        if (!is_array($msg) || ($msg['type'] ?? '') !== 'StartQuestion') {
            $this->emit($output, ['type' => 'Error', 'code' => 'BAD_INPUT', 'message' => 'Invalid payload']);
            return Command::FAILURE;
        }

        $rid = (string)$msg['requestId'];
        $question = (string)$msg['question'];
        try {
            $this->emit($output, ['type' => 'Ack', 'requestId' => $rid]);

            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'collect_context', 'pct' => 10]);
            $ctx = $this->collectContext($question);
            if ($this->checkTerminate()) return Command::SUCCESS;

            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'load_history', 'pct' => 30]);
            $hist = $this->loadHistory($question);
            if ($this->checkTerminate()) return Command::SUCCESS;

            $params = $this->bundle($ctx, $hist);
            $this->emit($output, ['type' => 'Progress', 'requestId' => $rid, 'phase' => 'llm', 'pct' => 60]);

            $this->streamOpenAI($params, function (string $delta) use ($output, $rid) {
                $this->emit($output, ['type' => 'StreamDelta', 'requestId' => $rid, 'delta' => $delta]);
            });

            $this->emit($output, ['type' => 'Citations', 'requestId' => $rid, 'items' => $ctx['citations'] ?? []]);
            $this->emit($output, ['type' => 'Done', 'requestId' => $rid, 'finishReason' => 'stop', 'usage' => []]);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->emit($output, ['type' => 'Error', 'requestId' => $rid, 'code' => $e->getCode(), 'message' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    private function checkTerminate(): bool
    {
        return $this->terminate === true;
    }

    private function emit(OutputInterface $out, array $msg): void
    {
        // Write NDJSON; flush to make sure UI sees it immediately
        $line = json_encode($msg, JSON_UNESCAPED_UNICODE) . "\n";
        // Use STDOUT directly to avoid buffering surprises:
        \fwrite(STDOUT, $line);
        \fflush(STDOUT);
    }

    private function collectContext(string $question): array {
        return [
            'citations' => [],
            'question' => $question
        ];
    }
    private function loadHistory(string $q): array {
        return [];
    }
    private function bundle(array $ctx, array $hist): array
    {
        // TODO add history later
        // TODO add system prompt depending on mode
        // TODO add citations data to context
        $params = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $ctx['question']
                ]
            ],
        ];

        return $params;
    }

    private function streamOpenAI(array $params, callable $onDelta): void
    {
         foreach ($this->agent->largeModel->completionStreamed($params) as $event) {
             $delta = $event->choices[0]->delta->content ?? '';
             if ($delta !== '') $onDelta($delta);
             if ($this->checkTerminate()) return;
         }
    }
}
