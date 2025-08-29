<?php

declare(strict_types=1);

namespace App\Command;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Tui\Application;
use App\Tui\Command\Runner;
use App\Tui\Exception\ExitInterruptException;
use App\Tui\Exception\UserInterruptException;
use App\Tui\State;
use App\Tui\Utility\InputUtilities;
use App\Tui\Utility\TerminalUtilities;
use PhpTui\Term\Actions;
use PhpTui\Term\ClearType;
use PhpTui\Term\Terminal;
use PhpTui\Tui\Bridge\PhpTerm\PhpTermBackend as PhpTuiPhpTermBackend;
use PhpTui\Tui\DisplayBuilder;
use PhpTui\Tui\Extension\Bdf\BdfExtension;
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
        private LoggerInterface $logger,
        private Terminal $terminal,
        private Agent $agent,
        private State $state,
        private Runner $runner,
        private Application $app,
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
        $display = DisplayBuilder::default(PhpTuiPhpTermBackend::new($this->terminal))
            ->addExtension(new BdfExtension())
            ->build();
        try {
            // enable "raw" mode to remove default terminal behavior (e.g.
            // echoing key presses)
            // switch to the "alternate" screen so that we can return the user where they left off
            $this->terminal->execute(Actions::alternateScreenEnable());
            $this->terminal->execute(Actions::cursorHide());
            $this->terminal->enableRawMode();

            // main loop
            while (true) { // @phpstan-ignore while.alwaysTrue
                $this->app->listenTerminalEvents();
                $display->draw($this->app->layout());
                [, $caretLine, $caretCol] =
                    InputUtilities::wrapTextAndLocateCaret(
                        $this->state->getInput(),
                        $this->state->getCharIndex(),
                        TerminalUtilities::getTerminalInnerWidth($this->terminal)
                    );
                TerminalUtilities::moveCursorToInputBox($this->terminal, $caretLine, $caretCol, $this->state->getScrollTopLine());
                $this->terminal->execute(Actions::cursorShow());

                usleep(20_000);
            }
        } catch (UserInterruptException|ExitInterruptException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'trace' => $e->getTrace(),
            ]);
            throw $e;
        } finally {
            $this->terminal->disableRawMode();
            $this->terminal->execute(Actions::alternateScreenDisable());
            $this->terminal->execute(Actions::cursorShow());
            $this->terminal->execute(Actions::clear(ClearType::All));
        }
    }
}
