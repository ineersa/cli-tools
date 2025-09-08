<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Tui\Exception\ExitInterruptException;
use App\Tui\Exception\UserInterruptException;
use App\Tui\Loop\LoopRunner;
use App\Worker\ConsumerAsyncWorker;
use App\Worker\ConsumerSummaryWorker;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Terminal;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:client',
    description: 'REPL with header, history cards (content-width), boxed input, autocomplete, and status bar.',
)]
final class AiClientCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Terminal $terminal,
        private readonly LoopRunner $loopRunner,
        private readonly ConsumerAsyncWorker $consumerWorker,
        private readonly ConsumerSummaryWorker $consumerSummaryWorker,
        private readonly Agent $agent,
    ) {
        parent::__construct();
    }

    /**
     * @param ConsoleOutput $output
     *
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // enable "raw" mode to remove default terminal behavior (e.g.
            // echoing key presses)
            // switch to the "alternate" screen so that we can return the user where they left off
            $this->terminal->execute(Actions::alternateScreenEnable());
            $this->terminal->execute(Actions::cursorHide());
            $this->terminal->enableRawMode();
            // starting background consumer
            $this->consumerWorker->start(uniqid('consumer_async_'));
            $this->consumerSummaryWorker->start(uniqid('consumer_summary_'));
            $this->agent->cleanUpChat();
            $this->loopRunner->boot();

            while (true) { // @phpstan-ignore while.alwaysTrue
                $this->loopRunner->tick();
                $this->loopRunner->sleep(1, 8);
            }
        } catch (UserInterruptException|ExitInterruptException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        } finally {
            $this->agent->cleanUpChat();
            $this->terminal->disableRawMode();
            $this->terminal->execute(Actions::alternateScreenDisable());
            $this->terminal->execute(Actions::cursorShow());
            $this->terminal->execute(Actions::clear(ClearType::All));
        }
    }
}
