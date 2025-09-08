<?php

namespace App\Worker;

use App\Agent\Agent;
use App\Tui\Component\ContentItemFactory;
use App\Tui\Component\ProblemComponent;
use App\Tui\Component\ProgressComponent;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class ConsumerSummaryWorker implements WorkerInterface
{
    private Process $process;

    private string $requestId;
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private LoggerInterface $logger,
        private Agent $agent,
    ) {}

    public function start(string $requestId): void
    {
        if (isset($this->process) && $this->process->isRunning()) {
            return;
        }

        $this->process = new Process([PHP_BINARY, $this->projectDir.'/bin/console', 'messenger:consume', 'summary', '--no-interaction', '--silent']);
        $this->process->setTimeout(null);
        $this->process->start();
        $this->agent->attachConsumer($requestId, $this);
        $this->requestId = $requestId;
    }

    public function poll(string $requestId): void
    {
        if (!$this->process->isRunning()) {
            $this->logger->info('Consumer is not running, starting it over');
            $this->start($requestId);
            throw new ProblemException('Summary consumer restarted');
        }
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->signal(SIGTERM);
            $this->process->wait();
            $this->agent->detachConsumer($this->requestId);
            throw new ProblemException('Summary consumer process terminated.');
        }
    }
}
