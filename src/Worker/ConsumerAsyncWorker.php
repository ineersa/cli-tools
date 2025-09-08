<?php

declare(strict_types=1);

namespace App\Worker;

use App\Agent\Agent;
use App\Tui\Exception\ProblemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class ConsumerAsyncWorker implements WorkerInterface
{
    private Process $process;

    private string $requestId;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private LoggerInterface $logger,
        private Agent $agent,
    ) {
    }

    public function start(string $requestId): void
    {
        if (isset($this->process) && $this->process->isRunning()) {
            return;
        }

        $this->process = new Process([\PHP_BINARY, $this->projectDir.'/bin/console', 'messenger:consume', 'async', '--no-interaction', '--silent']);
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
            throw new ProblemException('Consumer restarted');
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
            $this->agent->detachConsumer($this->requestId);
            throw new ProblemException('Consumer process terminated.');
        }
    }
}
